<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Helpers\Response;
use App\Middleware\JwtMiddleware;
use App\Models\Admin;
use App\Models\Message;
use App\Router;
use App\Services\EmailService;
use App\Services\RateLimiter;
use App\Support\ErrorHandler;

require __DIR__ . '/vendor/autoload.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'");

try {
    $env = require __DIR__ . '/config/env.php';
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error',
    ]);
    exit;
}

$logFile = __DIR__ . '/logs/error.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}
ErrorHandler::register($env['APP_ENV'] ?? 'development', $logFile);

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

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestMethod === 'GET' && $requestPath === '/api/health') {
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = require __DIR__ . '/config/database.php';
} catch (Throwable $exception) {
    ErrorHandler::log($exception, $logFile);
    Response::json([
        'success' => false,
        'message' => 'Server error',
    ], 500);
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
    $requestMethod,
    $requestPath
);
