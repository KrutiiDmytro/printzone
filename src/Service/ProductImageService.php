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
    private const FALLBACK = 'product-1.png';

    /** @var array<string, string> cache buster per file, memoised for the request */
    private array $versions = [];

    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $publicDir,
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
     * Seeded images keep their filename across releases, but nginx serves /img
     * with `Cache-Control: public, immutable, max-age=30d`. Without something
     * changing in the URL, a returning visitor keeps the previous release's
     * picture for a month — the browser is told not to even revalidate. The
     * file's mtime moves whenever a deploy rsyncs new content, so it is enough
     * to bust exactly the images that actually changed.
     */
    private function staticUrl(string $image): string
    {
        if (!isset($this->versions[$image])) {
            $path = $this->publicDir.'/img/'.$image;
            $this->versions[$image] = is_file($path) ? '?v='.filemtime($path) : '';
        }

        return '/img/'.$image.$this->versions[$image];
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
