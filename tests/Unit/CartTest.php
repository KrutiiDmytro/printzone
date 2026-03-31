<?php

namespace App\Tests\Unit\Cart\Domain\Entity;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Entity\Category;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    private Cart $cart;
    private User $user;

    protected function setUp(): void
    {
        $this->user = $this->createMock(User::class);
        $this->cart = new Cart();
        $this->cart->setUser($this->user);
    }

    public function testGetTotalReturnsZeroWhenEmpty(): void
    {
        $this->assertEquals(0, $this->cart->getTotal());
    }

    public function testGetTotalCalculatesCorrectly(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setPrice(10000); // 100.00 EUR
        $product1->setCategory($category);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setPrice(5000); // 50.00 EUR
        $product2->setCategory($category);

        $item1 = new CartItem();
        $item1->setProduct($product1);
        $item1->setQuantity(2);

        $item2 = new CartItem();
        $item2->setProduct($product2);
        $item2->setQuantity(3);

        $this->cart->addItem($item1);
        $this->cart->addItem($item2);

        // 2 * 10000 + 3 * 5000 = 20000 + 15000 = 35000
        $this->assertEquals(35000, $this->cart->getTotal());
    }

    public function testAddItemAddsItemToCart(): void
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
        $item->setQuantity(1);

        $this->cart->addItem($item);

        $this->assertCount(1, $this->cart->getItems());
        $this->assertTrue($this->cart->getItems()->contains($item));
        $this->assertEquals($this->cart, $item->getCart());
    }

    public function testAddItemDoesNotAddDuplicate(): void
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
        $item->setQuantity(1);

        $this->cart->addItem($item);
        $this->cart->addItem($item);

        $this->assertCount(1, $this->cart->getItems());
    }

    public function testRemoveItemRemovesItemFromCart(): void
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
        $item->setQuantity(1);

        $this->cart->addItem($item);
        $this->cart->removeItem($item);

        $this->assertCount(0, $this->cart->getItems());
        $this->assertNull($item->getCart());
    }

    public function testClearRemovesAllItems(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setPrice(10000);
        $product1->setCategory($category);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setPrice(5000);
        $product2->setCategory($category);

        $item1 = new CartItem();
        $item1->setProduct($product1);
        $item1->setQuantity(1);

        $item2 = new CartItem();
        $item2->setProduct($product2);
        $item2->setQuantity(1);

        $this->cart->addItem($item1);
        $this->cart->addItem($item2);

        $this->cart->clear();

        $this->assertCount(0, $this->cart->getItems());
        $this->assertEquals(0, $this->cart->getTotal());
    }
}