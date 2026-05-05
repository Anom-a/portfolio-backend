<?php

declare(strict_types=1);

namespace App;

use App\Helpers\Response;

final class Router
{
    /**
     * @var array<string, list<array{path: string, pattern: string, handler: callable}>>
     */
    private array $routes = [];

    private mixed $adminMiddleware = null;

    public function setAdminMiddleware(callable $middleware): void
    {
        $this->adminMiddleware = $middleware;
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        $route = $this->matchRoute($method, $path);

        if ($route === null) {
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

        ($route['handler'])(...$route['params']);
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $path = $this->normalizePath($path);

        $this->routes[strtoupper($method)][] = [
            'path' => $path,
            'pattern' => $this->routePattern($path),
            'handler' => $handler,
        ];
    }

    /**
     * @return array{handler: callable, params: list<string>}|null
     */
    private function matchRoute(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            return [
                'handler' => $route['handler'],
                'params' => array_values(array_filter(
                    $matches,
                    static fn (string|int $key): bool => is_string($key),
                    ARRAY_FILTER_USE_KEY
                )),
            ];
        }

        return null;
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

    private function routePattern(string $path): string
    {
        $segments = array_map(
            static function (string $segment): string {
                if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)}$/', $segment, $matches)) {
                    return '(?P<' . $matches[1] . '>[^/]+)';
                }

                return preg_quote($segment, '#');
            },
            explode('/', trim($path, '/'))
        );

        $pattern = '/' . implode('/', $segments);

        return '#^' . $pattern . '$#';
    }
}
