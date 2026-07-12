<?php

declare(strict_types=1);

namespace App\Service;

use App\Storage\Client\StorageClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Thin proxy to the Storage Service presign endpoint. STORAGE_TYPE stays in the
 * monolith (non-secret) so the admin UI can decide whether to offer the direct
 * S3 upload flow without a round-trip; the presigned request itself is minted
 * by the service.
 */
final class ProductImagePresignService
{
    public function __construct(
        private readonly StorageClient $storage,
        #[Autowire('%env(STORAGE_TYPE)%')]
        private readonly string $storageType,
    ) {
    }

    public function supportsPresign(): bool
    {
        return 's3' === strtolower(trim($this->storageType));
    }

    /**
     * @return array{url: string, key: string, method: string, headers: array<string, string>}
     */
    public function createPresignedPut(string $filename, string $contentType): array
    {
        if (!$this->supportsPresign()) {
            throw new \RuntimeException('Presigned upload is only available when STORAGE_TYPE=s3.');
        }

        return $this->storage->presignPut($filename, $contentType);
    }
}
