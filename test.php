<?php

require_once __DIR__ . "/vendor/autoload.php";

use FpDbTest\Database;
use FpDbTest\DatabaseTest;

spl_autoload_register(function ($class) {
    $a = array_slice(explode('\\', $class), 1);
    if (!$a) {
        throw new Exception();
    }
    $filename = implode('/', [__DIR__, ...$a]) . '.php';
    require_once $filename;
});

$mysqli = @new mysqli('localhost', 'root', '123', 'fpdbtest', 3306);
if ($mysqli->connect_errno) {
    exit(sprintf('MYSQL connection fail: %s\n', $mysqli->connect_error));
}

try {
    $db = new Database($mysqli);
    $test = new DatabaseTest($db);
    $test->testBuildQuery();
} catch (\Exception $e) {
    exit($e->getMessage());
}

exit('OK');
