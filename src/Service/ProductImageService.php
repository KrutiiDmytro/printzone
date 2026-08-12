<?php

declare(strict_types=1);

namespace App\Service;

use App\Storage\FileStorageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Local: EasyAdmin зберігає файл у тимчасовий каталог, потім запис у сховище.
 * S3: ключ `products/...` задається після presigned upload (див. ProductImagePresignService).
 */
final class ProductImageService
{
    private const PREFIX = 'products/';
    private const FALLBACK = 'product-1.png';

    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $packages,
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
            return $this->staticUrl(self::FALLBACK);
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

        return $this->staticUrl($image);
    }

    /**
     * Goes through the asset packages so seeded images pick up the same
     * mtime cache buster as every other static file — see
     * {@see \App\Asset\MtimeVersionStrategy} for why that is needed.
     */
    private function staticUrl(string $image): string
    {
        return $this->packages->getUrl('img/'.$image);
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
