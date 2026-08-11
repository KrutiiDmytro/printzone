<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Service\ProductImageService;
use App\Storage\FileStorageInterface;
use App\Twig\ProductImageExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductImageExtensionTest extends TestCase
{
    private function makeExtension(?string $publicDir = null): ProductImageExtension
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('publicUrl')->willReturn(null);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $params) => '/media?key='.($params['key'] ?? '')
        );

        // Default to a directory with no images: without a file to stat there is
        // no cache buster, so these assertions stay about the path itself.
        $service = new ProductImageService($storage, $urlGenerator, $publicDir ?? '/nonexistent');

        return new ProductImageExtension($service);
    }

    public function testGetFunctionsRegistersProductImageUrl(): void
    {
        $functions = $this->makeExtension()->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('product_image_url', $functions[0]->getName());
    }

    public function testProductImageUrlWithNullReturnsDefault(): void
    {
        $this->assertSame('/img/product-1.png', $this->makeExtension()->productImageUrl(null));
    }

    public function testProductImageUrlWithEmptyStringReturnsDefault(): void
    {
        $this->assertSame('/img/product-1.png', $this->makeExtension()->productImageUrl(''));
    }

    public function testProductImageUrlWithPlainFilenameReturnsImgPath(): void
    {
        $this->assertSame('/img/product.jpg', $this->makeExtension()->productImageUrl('product.jpg'));
    }

    public function testProductImageUrlWithStorageKeyUsesMediaProxy(): void
    {
        $result = $this->makeExtension()->productImageUrl('products/test.jpg');
        $this->assertSame('/media?key=products/test.jpg', $result);
    }

    /**
     * nginx serves /img as immutable for 30 days, so a seeded image that keeps
     * its filename across releases would stay stale in the browser. An existing
     * file must therefore carry its mtime in the query string.
     */
    public function testExistingStaticImageCarriesACacheBuster(): void
    {
        $dir = sys_get_temp_dir().'/pz-img-'.uniqid();
        mkdir($dir.'/img', 0o777, true);
        $file = $dir.'/img/product-1.png';
        file_put_contents($file, 'x');

        try {
            $result = $this->makeExtension($dir)->productImageUrl('product-1.png');
            $this->assertSame('/img/product-1.png?v='.filemtime($file), $result);
        } finally {
            unlink($file);
            rmdir($dir.'/img');
            rmdir($dir);
        }
    }

    public function testMissingStaticImageHasNoCacheBuster(): void
    {
        $result = $this->makeExtension(sys_get_temp_dir())->productImageUrl('does-not-exist.png');
        $this->assertSame('/img/does-not-exist.png', $result);
    }
}
