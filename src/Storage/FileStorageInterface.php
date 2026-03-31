<?php

declare(strict_types=1);

namespace App\Storage;

interface FileStorageInterface
{
    public function write(string $key, string $contents, ?string $contentType = null): void;

    public function read(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    public function publicUrl(string $key): ?string;
}