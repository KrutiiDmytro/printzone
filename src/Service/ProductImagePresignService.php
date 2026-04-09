<?php

declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Генерує presigned PUT для прямого завантаження з браузера в S3 (варіант А).
 */
final class ProductImagePresignService
{
    private const PREFIX = 'products/';

    /** @var array<string, true> */
    private const ALLOWED_MIME = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/gif' => true,
        'image/webp' => true,
    ];

    public function __construct(
        private readonly S3Client $s3Client,
        #[Autowire('%aws.s3.bucket%')]
        private readonly string $bucket,
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

        $contentType = strtolower(trim($contentType));
        if (!isset(self::ALLOWED_MIME[$contentType])) {
            throw new \InvalidArgumentException('Unsupported content type. Allowed: image/jpeg, image/png, image/gif, image/webp.');
        }

        $safeName = $this->sanitizeFilename($filename);
        if ('' === $safeName) {
            throw new \InvalidArgumentException('Invalid filename.');
        }

        $unique = bin2hex(random_bytes(16));
        $key = self::PREFIX.$unique.'-'.$safeName;

        $command = $this->s3Client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $request = $this->s3Client->createPresignedRequest($command, '+15 minutes');
        $url = (string) $request->getUri();

        return [
            'url' => $url,
            'key' => $key,
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ];
    }

    private function sanitizeFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base) ?? '';
        $base = trim($base, '._-');
        if (strlen($base) > 180) {
            $ext = pathinfo($base, \PATHINFO_EXTENSION);
            $name = pathinfo($base, \PATHINFO_FILENAME);
            $base = substr($name, 0, 160).(strlen($ext) > 0 ? '.'.$ext : '');
        }

        return $base;
    }
}
