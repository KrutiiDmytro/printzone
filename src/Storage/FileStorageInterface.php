<?php

declare(strict_types=1);

namespace App\Storage;

interface FileStorageInterface
{
    public function write(string $key, string $contents, ?string $contentType = null): void;

    public function read(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    /**
     * @return list<string> Paths to files only (directories excluded), relative to storage root.
     */
    public function listKeys(string $prefix = '', bool $deep = false): array;

    public function publicUrl(string $key): ?string;
}