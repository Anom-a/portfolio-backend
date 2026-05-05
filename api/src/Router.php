<?php

declare(strict_types=1);

namespace App;

use App\Helpers\Response;

final class Router
{
    /**
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    private mixed $adminMiddleware = null;

    public function setAdminMiddleware(callable $middleware): void
    {
        $this->adminMiddleware = $middleware;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            Response::json([
                'success' => false,
                'message' => 'Not found',
            ], 404);
            return;
        }

        if ($this->isProtectedAdminRoute($path) && is_callable($this->adminMiddleware)) {
            $authorized = ($this->adminMiddleware)();

            if ($authorized === false) {
                return;
            }
        }

        $handler();
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : $normalized;
    }

    private function isProtectedAdminRoute(string $path): bool
    {
        return str_starts_with($path, '/api/admin/')
            && $path !== '/api/admin/login';
    }
}
