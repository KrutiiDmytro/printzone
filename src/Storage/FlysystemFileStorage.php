<?php

declare(strict_types=1);

namespace App\Storage;

use Aws\S3\S3Client;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;

final class FlysystemFileStorage implements FileStorageInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly ?string $s3Bucket = null,
        private readonly ?S3Client $s3Client = null,
    ) {
    }

    public function write(string $key, string $contents, ?string $contentType = null): void
    {
        $key = $this->sanitizeKey($key);
        $config = [];
        if (null !== $contentType) {
            $config['ContentType'] = $contentType;
        }
        $this->filesystem->write($key, $contents, $config);
    }

    public function read(string $key): string
    {
        $key = $this->sanitizeKey($key);
        try {
            return $this->filesystem->read($key);
        } catch (UnableToReadFile $e) {
            throw new \RuntimeException(\sprintf('Cannot read: %s', $key), 0, $e);
        } catch (FilesystemException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function delete(string $key): void
    {
        $key = $this->sanitizeKey($key);
        $this->filesystem->delete($key);
    }

    public function exists(string $key): bool
    {
        $key = $this->sanitizeKey($key);

        return $this->filesystem->fileExists($key);
    }

    public function publicUrl(string $key): ?string
    {
        $key = $this->sanitizeKey($key);
        if (null !== $this->s3Client && null !== $this->s3Bucket) {
            return $this->s3Client->getObjectUrl($this->s3Bucket, $key);
        }

        return null;
    }

    private function sanitizeKey(string $key): string
    {
        $key = str_replace('\\', '/', trim($key));
        if ('' === $key || str_contains($key, '..')) {
            throw new \InvalidArgumentException('Invalid storage key.');
        }

        return ltrim($key, '/');
    }
}