<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Middleware\JwtMiddleware;
use App\Models\Admin;
use App\Models\Message;
use App\Router;
use App\Services\EmailService;
use App\Services\RateLimiter;

require __DIR__ . '/vendor/autoload.php';

try {
    $env = require __DIR__ . '/config/env.php';
    $pdo = require __DIR__ . '/config/database.php';
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
    ]);
    exit;
}

$allowedOrigin = $env['FRONTEND_URL'] ?? '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
$router->setAdminMiddleware([new JwtMiddleware($env['JWT_SECRET']), 'handle']);

$contactController = new ContactController(
    new Message($pdo),
    new EmailService(
        $env['RESEND_API_KEY'],
        $env['ADMIN_EMAIL'],
        'send@anomabebe.tech'
    ),
    new RateLimiter()
);
$authController = new AuthController(
    new Admin($pdo),
    $env['JWT_SECRET']
);
$adminController = new AdminController($pdo);

$router->post('/api/contact', [$contactController, 'store']);
$router->post('/api/admin/login', [$authController, 'login']);
$router->post('/api/admin/logout', [$authController, 'logout']);
$router->get('/api/admin/messages', [$adminController, 'messages']);
$router->get('/api/admin/messages/{id}', [$adminController, 'showMessage']);
$router->patch('/api/admin/messages/{id}/read', [$adminController, 'updateReadStatus']);
$router->delete('/api/admin/messages/{id}', [$adminController, 'deleteMessage']);
$router->get('/api/admin/stats', [$adminController, 'stats']);
$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
