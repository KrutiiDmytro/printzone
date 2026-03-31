<?php

namespace App\Tests\Unit\Order\Domain\Entity;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Entity\Category;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    private Order $order;
    private User $user;

    protected function setUp(): void
    {
        $this->user = $this->createMock(User::class);
        $this->order = new Order();
        $this->order->setUser($this->user);
    }

    public function testAddItemAddsItemToOrder(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Product');
        $product->setPrice(10000);
        $product->setCategory($category);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(2);
        $item->setPrice(10000);

        $this->order->addItem($item);

        $this->assertCount(1, $this->order->getItems());
        $this->assertTrue($this->order->getItems()->contains($item));
        $this->assertEquals($this->order, $item->getOrderRef());
    }

    public function testRemoveItemRemovesItemFromOrder(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Product');
        $product->setPrice(10000);
        $product->setCategory($category);

        $item = new OrderItem();
        $item->setProduct($product);
        $item->setQuantity(2);
        $item->setPrice(10000);

        $this->order->addItem($item);
        $this->order->removeItem($item);

        $this->assertCount(0, $this->order->getItems());
        $this->assertNull($item->getOrderRef());
    }
}