<?php

declare(strict_types=1);

namespace App\Helpers;

final class Response
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
