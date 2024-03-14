<?php

namespace FpDbTest;

class ArrayUtils
{
    public static function isArrayAssoc(array $array): bool
    {
        return count(array_filter(array_keys($array), function ($key) {
            return preg_match("/^[a-z]+$/i", $key);
        })) != 0;
    }
}
