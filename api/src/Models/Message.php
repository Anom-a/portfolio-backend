<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Message
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{name: string, email: string, subject: string, message: string, ip_address: string} $message
     */
    public function create(array $message): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO messages (name, email, subject, message, ip_address)
             VALUES (:name, :email, :subject, :message, :ip_address)'
        );

        $statement->execute([
            'name' => $message['name'],
            'email' => $message['email'],
            'subject' => $message['subject'],
            'message' => $message['message'],
            'ip_address' => $message['ip_address'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
