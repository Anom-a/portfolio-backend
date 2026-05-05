<?php

declare(strict_types=1);

$env = $_ENV;

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('PDO MySQL driver is not installed. Install php8.1-mysql for MySQL support.');
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $env['DB_HOST'],
    $env['DB_NAME']
);

return new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
