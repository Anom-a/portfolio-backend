<?php

declare(strict_types=1);

$envPath = dirname(__DIR__) . '/.env';

if (!is_file($envPath)) {
    throw new RuntimeException('.env file not found.');
}

$variables = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines === false ? [] : $lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
    $key = trim($key);
    $value = trim($value);

    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, "'") && str_ends_with($value, "'"))
    ) {
        $value = substr($value, 1, -1);
    }

    $variables[$key] = $value;
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
}

$required = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'RESEND_API_KEY',
    'ADMIN_EMAIL',
    'ADMIN_PASSWORD',
    'JWT_SECRET',
    'APP_URL',
];

foreach ($required as $key) {
    if (($variables[$key] ?? '') === '') {
        throw new RuntimeException("Missing required environment variable: {$key}");
    }
}

if (strlen($variables['JWT_SECRET']) < 32) {
    throw new RuntimeException('JWT_SECRET must be at least 32 characters.');
}

return $variables;
