<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Support\RequestContext;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtMiddleware
{
    public function __construct(private readonly string $jwtSecret)
    {
    }

    public function handle(): bool
    {
        $token = $this->bearerToken();

        if ($token === null) {
            return $this->unauthorized();
        }

        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        } catch (Throwable) {
            return $this->unauthorized();
        }

        RequestContext::set('jwt', (array) $payload);

        return true;
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function unauthorized(): bool
    {
        Response::json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);

        return false;
    }
}
