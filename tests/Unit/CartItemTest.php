<?php

namespace App\Tests\Unit\Cart\Domain\Entity;

use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Entity\Category;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    public function testGetTotalCalculatesCorrectly(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Product');
        $product->setPrice(10000); // 100.00 EUR
        $product->setCategory($category);

        $item = new CartItem();
        $item->setProduct($product);
        $item->setQuantity(3);

        // 3 * 10000 = 30000
        $this->assertEquals(30000, $item->getTotal());
    }

    public function testGetTotalReturnsZeroWhenQuantityIsZero(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Product');
        $product->setPrice(10000);
        $product->setCategory($category);

        $item = new CartItem();
        $item->setProduct($product);
        $item->setQuantity(0);

        $this->assertEquals(0, $item->getTotal());
    }
}