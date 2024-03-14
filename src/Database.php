<?php

namespace FpDbTest;

use mysqli;
use FpDbTest\ArrayUtils;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private $cursor = 0;
    private $argCursor = 0;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        // reset state
        $this->cursor    = -1;
        $this->argCursor = -1;

        $result = '';
        $len = strlen($query);
        while (++$this->cursor < $len) {
            $char = $query[$this->cursor];

            if ($char === '?') {
                $nextChar = $this->cursor < $len - 1 ? $query[$this->cursor + 1] : null;
                $result .= $this->insertParamValue($nextChar, $args[++$this->argCursor]);
            } else if ($char === '{') {

                $start = $this->cursor;
                $j = $start;

                // find } symbol
                while ($j < $len) {
                    $j++;
                    if ($j == $len)
                        throw new \Exception("Condition block was not closed");
                    
                    if ($query[$j] === '}') break;
                }

                $value = $args[++$this->argCursor];
                if ($value !== "SKIP_BLOCK") {
                    $tmp = substr($query, 0, $start); // before block
                    $tmp.= substr($query, $start + 1, $j - $start - 1); // block
                    $tmp.= substr($query, $j + 1, $len - $j); // after block

                    $query = $tmp;

                    $this->jumpTo($start - 1);
                    $this->argCursor--;

                    $len -= 2;
                } else {
                    $this->jumpTo($j + 1);
                }
            } else {
                $result .= $char;
            }
        }
        
        return $result;
    }

    private function next()
    {
        return ++$this->cursor;
    }

    private function jumpTo($p)
    {
        $this->cursor = $p;
    }

    private function insertParamValue(?string $specifier, $value): string
    {
        switch ($specifier) {
            case 'd':
                $this->next(); // skip specifier
                return is_null($value) ? 'NULL' : intval($value);
            case 'f':
                $this->next(); // skip specifier
                return is_null($value) ? 'NULL' : floatval($value);
            case 'a':
                $this->next(); // skip specifier
                return $this->insertArrayValue($value);
            case '#':
                $this->next(); // skip specifier
                return $this->insertIdentifierValue($value);
            default:
                return $this->escapeValue($value);
        }
    }

    private function insertArrayValue(array $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (ArrayUtils::isArrayAssoc($value)) {
            $pairs = [];
            foreach ($value as $column => $v) {
                $pairs[] = $this->insertIdentifierValue($column) . ' = ' . $this->escapeValue($v);
            }
            return implode(', ', $pairs);
        } else if (is_array($value[0])) {
            $pairs = [];
            foreach ($value as $pair) {
                $pairs[] = $this->insertIdentifierValue($pair[0]) . ' = ' . $this->escapeValue($pair[1]);
            }
            return implode(', ', $pairs);
        } else {
            return implode(', ', array_map(fn ($v) => $this->escapeValue($v), $value));
        }
    }

    private function insertIdentifierValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => '`' . $this->mysqli->real_escape_string($v) . '`', $value));
        } else {
            return '`' . $this->mysqli->real_escape_string($value) . '`';
        }
    }

    private function escapeValue($value): string
    {
        return is_null($value)
            ? 'NULL'
            : (is_numeric($value) ? $value : "'" . $this->mysqli->real_escape_string($value) . "'");
    }

    public function skip()
    {
        return 'SKIP_BLOCK';
    }
}
