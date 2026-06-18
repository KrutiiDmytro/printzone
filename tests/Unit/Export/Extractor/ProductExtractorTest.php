<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Extractor;

use App\Export\Client\CatalogProductClient;
use App\Export\Extractor\ProductExtractor;
use PHPUnit\Framework\TestCase;

final class ProductExtractorTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $products
     */
    private function clientReturning(array $products): CatalogProductClient
    {
        $client = $this->createMock(CatalogProductClient::class);
        // One page of results, then the extractor stops (page < PAGE_SIZE).
        $client->method('fetchProducts')->willReturn(['data' => $products, 'total' => count($products)]);

        return $client;
    }

    public function testExtractReturnsEmptyArrayWhenNoProducts(): void
    {
        $extractor = new ProductExtractor($this->clientReturning([]));

        self::assertSame([], $extractor->extract([]));
    }

    public function testExtractTransformsProduct(): void
    {
        $product = [
            'id' => 'a1b2',
            'name' => 'Laptop',
            'description' => 'A laptop',
            'price' => 99900,
            'stock' => 10,
            'isFeatured' => true,
            'image' => 'products/laptop.jpg',
            'category' => ['id' => 'c1', 'name' => 'Electronics', 'slug' => 'electronics'],
        ];

        $result = (new ProductExtractor($this->clientReturning([$product])))->extract([]);

        self::assertCount(1, $result);
        self::assertSame('a1b2', $result[0]['id']);
        self::assertSame('Laptop', $result[0]['name']);
        self::assertSame('999.00', $result[0]['price']);
        self::assertSame(10, $result[0]['stock']);
        self::assertSame('yes', $result[0]['is_featured']);
        self::assertSame('Electronics', $result[0]['category']);
        self::assertSame('products/laptop.jpg', $result[0]['image']);
    }

    public function testExtractIsFeaturedFalseOutputsNo(): void
    {
        $product = ['id' => '2', 'name' => 'X', 'price' => 0, 'stock' => 0, 'isFeatured' => false, 'category' => null];

        $result = (new ProductExtractor($this->clientReturning([$product])))->extract([]);

        self::assertSame('no', $result[0]['is_featured']);
    }

    public function testExtractHandlesNullOptionalFields(): void
    {
        $product = ['id' => '3', 'name' => 'Y', 'price' => 500, 'stock' => 5, 'isFeatured' => false];

        $result = (new ProductExtractor($this->clientReturning([$product])))->extract([]);

        self::assertSame('', $result[0]['description']);
        self::assertSame('', $result[0]['image']);
        self::assertSame('', $result[0]['category']);
    }

    public function testExtractForwardsFiltersToClient(): void
    {
        $client = $this->createMock(CatalogProductClient::class);
        $client->expects($this->once())
            ->method('fetchProducts')
            ->with(['isFeatured' => '1'], 1, 500)
            ->willReturn(['data' => [], 'total' => 0]);

        (new ProductExtractor($client))->extract(['isFeatured' => '1']);
    }
}
