<?php

namespace App\Tests\Unit\Order\Domain\Entity;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    private Order $order;

    protected function setUp(): void
    {
        $this->order = new Order();
        $this->order->setUserId(1);
    }

    private function makeItem(): OrderItem
    {
        $item = new OrderItem();
        $item->setProductId(1);
        $item->setProductName('Product');
        $item->setQuantity(2);
        $item->setPrice(10000);

        return $item;
    }

    public function testAddItemAddsItemToOrder(): void
    {
        $item = $this->makeItem();

        $this->order->addItem($item);

        $this->assertCount(1, $this->order->getItems());
        $this->assertTrue($this->order->getItems()->contains($item));
        $this->assertEquals($this->order, $item->getOrderRef());
    }

    public function testRemoveItemRemovesItemFromOrder(): void
    {
        $item = $this->makeItem();

        $this->order->addItem($item);
        $this->order->removeItem($item);

        $this->assertCount(0, $this->order->getItems());
        $this->assertNull($item->getOrderRef());
    }

    public function testOrderInitialization(): void
    {
        $order = new Order();

        $this->assertInstanceOf(\DateTimeInterface::class, $order->getCreatedAt());
        $this->assertEquals('PENDING', $order->getStatus());
        $this->assertEquals(0, $order->getTotalAmount());
        $this->assertCount(0, $order->getItems());
    }

    public function testSetAndGetUserId(): void
    {
        $order = new Order();

        $order->setUserId(7);
        $this->assertSame(7, $order->getUserId());
    }

    public function testSetTotalAmount(): void
    {
        $order = new Order();
        $order->setTotalAmount(5000); // 50.00

        $this->assertEquals(5000, $order->getTotalAmount());
    }

    public function testStatusChange(): void
    {
        $order = new Order();
        $order->setStatus('SHIPPED');

        $this->assertEquals('SHIPPED', $order->getStatus());
    }
}
