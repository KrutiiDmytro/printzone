<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Catalog\Client\CatalogClient;
use App\Catalog\View\CategoryView;
use App\Twig\CategoryExtension;
use PHPUnit\Framework\TestCase;

final class CategoryExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersGetCategories(): void
    {
        $extension = new CategoryExtension($this->createMock(CatalogClient::class));

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('get_categories', $functions[0]->getName());
    }

    public function testGetCategoriesDelegatesToCatalogClient(): void
    {
        $category = CategoryView::fromArray(['id' => 'c1', 'name' => 'Cat', 'slug' => 'cat']);

        $catalog = $this->createMock(CatalogClient::class);
        $catalog->expects($this->once())
            ->method('rootCategories')
            ->willReturn([$category]);

        $result = (new CategoryExtension($catalog))->getCategories();

        $this->assertSame([$category], $result);
    }

    public function testGetCategoriesReturnsEmptyArrayWhenNoneExist(): void
    {
        $catalog = $this->createMock(CatalogClient::class);
        $catalog->method('rootCategories')->willReturn([]);

        $result = (new CategoryExtension($catalog))->getCategories();

        $this->assertSame([], $result);
    }
}
