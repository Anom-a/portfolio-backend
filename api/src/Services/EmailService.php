<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class EmailService
{
    private const RESEND_ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $adminEmail,
        private readonly string $fromEmail
    ) {
    }

    /**
     * @param array{name: string, email: string, subject: string, message: string, ip_address: string} $message
     */
    public function sendContactNotification(array $message): void
    {
        $payload = [
            'from' => $this->fromEmail,
            'to' => [$this->adminEmail],
            'subject' => 'New contact: ' . $message['subject'],
            'html' => $this->htmlBody($message),
        ];

        $ch = curl_init(self::RESEND_ENDPOINT);

        if ($ch === false) {
            throw new RuntimeException('Unable to initialize email request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Resend API request failed: ' . ($error ?: (string) $response));
        }
    }

    /**
     * @param array{name: string, email: string, subject: string, message: string, ip_address: string} $message
     */
    private function htmlBody(array $message): string
    {
        $name = htmlspecialchars($message['name'], ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($message['email'], ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($message['subject'], ENT_QUOTES, 'UTF-8');
        $body = nl2br(htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8'));
        $ipAddress = htmlspecialchars($message['ip_address'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <h2>New contact form message</h2>
            <p><strong>Name:</strong> {$name}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>IP address:</strong> {$ipAddress}</p>
            <hr>
            <p>{$body}</p>
        HTML;
    }
}
