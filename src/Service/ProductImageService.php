<?php

declare(strict_types=1);

namespace App\Service;

use App\Storage\FileStorageInterface;
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
            // Presigned GET lets the browser fetch a private-bucket object directly
            // from S3 (null in local mode). If storage is unavailable we degrade to
            // the /media proxy rather than breaking the page render.
            try {
                $public = $this->storage->publicUrl($image);
            } catch (\Throwable) {
                $public = null;
            }

            return $public ?? $this->urlGenerator->generate('app_media', ['key' => $image]);
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
