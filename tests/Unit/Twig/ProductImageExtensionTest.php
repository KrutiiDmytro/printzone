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
    private function makeExtension(string $storageType = 'local'): ProductImageExtension
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('publicUrl')->willReturn(null);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $route, array $params) => '/media?key='.($params['key'] ?? '')
        );

        $service = new ProductImageService($storage, $urlGenerator, '/tmp', '/tmp/uploads', $storageType);

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
}
