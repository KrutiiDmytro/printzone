<?php

declare(strict_types=1);

namespace App\Service;

use App\Storage\FileStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Local: EasyAdmin зберігає файл у тимчасовий каталог, потім запис у сховище.
 * S3: ключ `products/...` задається після presigned upload (див. ProductImagePresignService).
 */
final class ProductImageService
{
    private const PREFIX = 'products/';

    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(STORAGE_TYPE)%')]
        private readonly string $storageType,
    ) {
    }

    public function deleteStoredImageIfAny(?string $imageKey): void
    {
        if (null !== $imageKey && str_starts_with($imageKey, self::PREFIX)) {
            $this->safeDelete($imageKey);
        }
    }

    public function getUrlForDisplay(?string $image): string
    {
        if (null === $image || '' === $image) {
            return '/img/product-1.png';
        }

        if (str_starts_with($image, self::PREFIX)) {
            // Приватний S3: прямий getObjectUrl часто дає 403; показ через проксі.
            if ('s3' === strtolower(trim($this->storageType))) {
                return $this->urlGenerator->generate('app_media', ['key' => $image]);
            }

            $public = $this->storage->publicUrl($image);
            if (null !== $public) {
                return $public;
            }

            return $this->urlGenerator->generate('app_media', ['key' => $image]);
        }

        return '/img/'.$image;
    }

    private function safeDelete(string $key): void
    {
        try {
            if ($this->storage->exists($key)) {
                $this->storage->delete($key);
            }
        } catch (\Throwable) {
        }
    }
}
