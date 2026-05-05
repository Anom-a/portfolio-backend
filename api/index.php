<?php

declare(strict_types=1);

use App\Controllers\ContactController;
use App\Models\Message;
use App\Router;
use App\Services\EmailService;
use App\Services\RateLimiter;

require __DIR__ . '/vendor/autoload.php';

$env = require __DIR__ . '/config/env.php';
$pdo = require __DIR__ . '/config/database.php';

$allowedOrigin = $env['FRONTEND_URL'] ?? '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
$contactController = new ContactController(
    new Message($pdo),
    new EmailService(
        $env['RESEND_API_KEY'],
        $env['ADMIN_EMAIL'],
        'send@anomabebe.tech'
    ),
    new RateLimiter()
);

$router->post('/api/contact', [$contactController, 'store']);
$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
