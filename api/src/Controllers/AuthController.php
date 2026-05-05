<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Sanitizer;
use App\Models\Admin;
use Firebase\JWT\JWT;
use JsonException;
use App\Support\ErrorHandler;
use Throwable;

final class AuthController
{
    private const TOKEN_TTL_SECONDS = 86400;

    public function __construct(
        private readonly Admin $admins,
        private readonly string $jwtSecret
    ) {
    }

    public function login(): void
    {
        try {
            $payload = Sanitizer::strings($this->jsonBody());
            $email = Sanitizer::email((string) ($payload['email'] ?? ''));
            $password = (string) ($payload['password'] ?? '');
            $admin = $this->admins->findByEmail($email);

            if ($admin === null || !password_verify($password, $admin['password_hash'])) {
                $this->invalidCredentials();
                return;
            }

            $issuedAt = time();
            $expiresAt = $issuedAt + self::TOKEN_TTL_SECONDS;

            $token = JWT::encode([
                'sub' => $admin['id'],
                'email' => $admin['email'],
                'iat' => $issuedAt,
                'exp' => $expiresAt,
            ], $this->jwtSecret, 'HS256');

            Response::json([
                'token' => $token,
                'expires_in' => self::TOKEN_TTL_SECONDS,
            ]);
        } catch (JsonException) {
            $this->invalidCredentials();
        } catch (Throwable $exception) {
            ErrorHandler::log($exception);
            $this->serverError();
        }
    }

    public function logout(): void
    {
        try {
            Response::json([
                'success' => true,
            ]);
        } catch (Throwable $exception) {
            ErrorHandler::log($exception);
            $this->serverError();
        }
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function jsonBody(): array
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function invalidCredentials(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Invalid credentials',
        ], 401);
    }

    private function serverError(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Server error',
        ], 500);
    }
}
