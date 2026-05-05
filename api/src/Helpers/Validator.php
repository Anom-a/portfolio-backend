<?php

declare(strict_types=1);

namespace App\Helpers;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function contact(array $data): array
    {
        $errors = [];

        foreach (['name', 'email', 'subject', 'message'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = 'Required';
            }
        }

        if (!isset($errors['email']) && !filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        if (!isset($errors['message'])) {
            $messageLength = mb_strlen(trim((string) $data['message']));

            if ($messageLength < 10 || $messageLength > 500) {
                $errors['message'] = 'Must be between 10 and 500 characters';
            }
        }

        return $errors;
    }
}
