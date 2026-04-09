<?php

declare(strict_types=1);

namespace App\Service;

use App\Storage\FileStorageInterface;

/**
 * Приклад використання FileStorageInterface (локальне сховище або S3 залежно від STORAGE_TYPE).
 */
final class FileStorageExampleService
{
    public function __construct(
        private readonly FileStorageInterface $fileStorage,
    ) {
    }

    public function saveText(string $key, string $contents): void
    {
        $this->fileStorage->write($key, $contents, 'text/plain; charset=UTF-8');
    }

    public function getText(string $key): string
    {
        return $this->fileStorage->read($key);
    }

    public function remove(string $key): void
    {
        $this->fileStorage->delete($key);
    }

    public function has(string $key): bool
    {
        return $this->fileStorage->exists($key);
    }

    public function urlOrNull(string $key): ?string
    {
        return $this->fileStorage->publicUrl($key);
    }
}
