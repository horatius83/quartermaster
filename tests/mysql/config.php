<?php
// Config for tests/mysql/check.php: the throwaway database container that tests/mysql/run.mjs starts.

return [
    'driver' => 'mysql',
    'mysql' => [
        'host'     => 'qm-mysql-check-db',
        'port'     => 3306,
        'database' => 'quartermaster',
        'username' => 'quartermaster',
        'password' => 'throwaway',
    ],
    'base_path' => null,
    'debug' => true,
];
