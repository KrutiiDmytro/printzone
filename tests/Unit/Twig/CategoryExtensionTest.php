<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Catalog\Domain\Entity\Category;
use App\Repository\CategoryRepository;
use App\Twig\CategoryExtension;
use PHPUnit\Framework\TestCase;

final class CategoryExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersGetCategories(): void
    {
        $repo = $this->createMock(CategoryRepository::class);
        $extension = new CategoryExtension($repo);

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('get_categories', $functions[0]->getName());
    }

    public function testGetCategoriesDelegatesToRepository(): void
    {
        $category = $this->createMock(Category::class);

        $repo = $this->createMock(CategoryRepository::class);
        $repo->expects($this->once())
            ->method('findAllRootCategories')
            ->willReturn([$category]);

        $result = (new CategoryExtension($repo))->getCategories();

        $this->assertSame([$category], $result);
    }

    public function testGetCategoriesReturnsEmptyArrayWhenNoneExist(): void
    {
        $repo = $this->createMock(CategoryRepository::class);
        $repo->method('findAllRootCategories')->willReturn([]);

        $result = (new CategoryExtension($repo))->getCategories();

        $this->assertSame([], $result);
    }
}
