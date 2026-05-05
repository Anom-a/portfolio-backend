<?php

declare(strict_types=1);

namespace App\Helpers;

final class Sanitizer
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function strings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim(strip_tags($value));
            }
        }

        return $data;
    }

    public static function email(string $email): string
    {
        return trim((string) filter_var(strip_tags($email), FILTER_SANITIZE_EMAIL));
    }
}
