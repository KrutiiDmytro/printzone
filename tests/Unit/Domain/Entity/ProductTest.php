<?php

namespace App\Tests\Unit\Domain\Entity;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testProductInitialization(): void
    {
        $product = new Product();
        
        $this->assertNull($product->getId());
        $this->assertNull($product->getName());
        $this->assertEquals(0, $product->getStock());
        $this->assertFalse($product->isFeatured());
    }

    public function testSetAndGetAttributes(): void
    {
        $product = new Product();
        
        $product->setName('Test Product');
        $this->assertEquals('Test Product', $product->getName());
        
        $product->setPrice(1000);
        $this->assertEquals(1000, $product->getPrice());
        
        $product->setStock(5);
        $this->assertEquals(5, $product->getStock());
        
        $product->setDescription('Description');
        $this->assertEquals('Description', $product->getDescription());
    }

    public function testCategoryAssociation(): void
    {
        $product = new Product();
        $category = new Category();
        
        $product->setCategory($category);
        $this->assertSame($category, $product->getCategory());
    }
}
