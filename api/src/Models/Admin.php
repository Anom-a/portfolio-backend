<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Admin
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{id: int, email: string, password_hash: string}|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, password_hash FROM admins WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $admin = $statement->fetch();

        if ($admin === false) {
            return null;
        }

        return [
            'id' => (int) $admin['id'],
            'email' => (string) $admin['email'],
            'password_hash' => (string) $admin['password_hash'],
        ];
    }
}
