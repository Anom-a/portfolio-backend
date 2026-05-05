<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Models\Message;
use App\Services\EmailService;
use App\Services\RateLimiter;
use App\Support\ErrorHandler;
use JsonException;
use Throwable;

final class ContactController
{
    public function __construct(
        private readonly Message $messages,
        private readonly EmailService $emailService,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function store(): void
    {
        $ipAddress = $this->clientIpAddress();

        if (!$this->rateLimiter->allow($ipAddress, 3, 3600)) {
            Response::json([
                'success' => false,
                'message' => 'Too many requests',
            ], 429);
            return;
        }

        try {
            $payload = $this->jsonBody();
        } catch (JsonException) {
            Response::json([
                'success' => false,
                'errors' => [
                    'body' => 'Invalid JSON body',
                ],
            ], 422);
            return;
        }

        $payload = Sanitizer::strings($payload);
        $payload['email'] = Sanitizer::email((string) ($payload['email'] ?? ''));
        $errors = Validator::contact($payload);

        if ($errors !== []) {
            Response::json([
                'success' => false,
                'errors' => $errors,
            ], 422);
            return;
        }

        $message = [
            'name' => trim((string) $payload['name']),
            'email' => trim((string) $payload['email']),
            'subject' => trim((string) $payload['subject']),
            'message' => trim((string) $payload['message']),
            'ip_address' => $ipAddress,
        ];

        try {
            $this->messages->create($message);
            $this->emailService->sendContactNotification($message);
        } catch (Throwable $exception) {
            ErrorHandler::log($exception);
            Response::json([
                'success' => false,
                'message' => 'Unable to send message',
            ], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Message sent',
        ], 201);
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

    private function clientIpAddress(): string
    {
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwardedFor !== '') {
            $firstIp = trim(explode(',', $forwardedFor)[0]);

            if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                return $firstIp;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
