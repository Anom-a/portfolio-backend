<?php

declare(strict_types=1);

namespace App\Services;

final class RateLimiter
{
    public function __construct(private readonly string $storagePath = '/tmp/contact-api-rate-limit')
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function allow(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $file = $this->filePath($key);
        $now = time();

        $attempts = $this->readAttempts($file);
        $attempts = array_values(array_filter(
            $attempts,
            static fn (int $timestamp): bool => $timestamp > ($now - $windowSeconds)
        ));

        if (count($attempts) >= $maxAttempts) {
            $this->writeAttempts($file, $attempts);
            return false;
        }

        $attempts[] = $now;
        $this->writeAttempts($file, $attempts);

        return true;
    }

    private function filePath(string $key): string
    {
        return $this->storagePath . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * @return list<int>
     */
    private function readAttempts(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        $decoded = json_decode((string) $contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $decoded),
            static fn (int $timestamp): bool => $timestamp > 0
        ));
    }

    /**
     * @param list<int> $attempts
     */
    private function writeAttempts(string $file, array $attempts): void
    {
        file_put_contents($file, json_encode($attempts, JSON_THROW_ON_ERROR), LOCK_EX);
    }
}
