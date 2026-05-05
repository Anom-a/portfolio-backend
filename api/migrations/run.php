<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
$env = require $root . '/config/env.php';
$pdo = require $root . '/config/database.php';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) PRIMARY KEY,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executedStatement = $pdo->query('SELECT migration FROM schema_migrations');
$executed = array_flip($executedStatement->fetchAll(PDO::FETCH_COLUMN));
$files = glob(__DIR__ . '/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $migration = basename($file);

    if (isset($executed[$migration])) {
        echo "Skipping {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Unable to read migration: {$migration}");
    }

    $sql = prepareMigrationSql($sql, $env);

    $pdo->exec($sql);

    $statement = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
    $statement->execute(['migration' => $migration]);

    echo "Applied {$migration}\n";
}

/**
 * @param array<string, string> $env
 */
function prepareMigrationSql(string $sql, array $env): string
{
    if (str_contains($sql, '{{ADMIN_EMAIL}}')) {
        $sql = str_replace('{{ADMIN_EMAIL}}', escapeSqlLiteral($env['ADMIN_EMAIL']), $sql);
    }

    if (str_contains($sql, '{{ADMIN_PASSWORD_BCRYPT_HASH}}')) {
        $hash = password_hash($env['ADMIN_PASSWORD'], PASSWORD_BCRYPT);
        $sql = str_replace('{{ADMIN_PASSWORD_BCRYPT_HASH}}', escapeSqlLiteral($hash), $sql);
    }

    return $sql;
}

function escapeSqlLiteral(string $value): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
}
