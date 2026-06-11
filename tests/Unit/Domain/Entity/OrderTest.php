<?php

namespace App\Tests\Unit\Domain\Entity;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testOrderInitialization(): void
    {
        $order = new Order();

        $this->assertInstanceOf(\DateTimeInterface::class, $order->getCreatedAt());
        $this->assertEquals('PENDING', $order->getStatus());
        $this->assertEquals(0, $order->getTotalAmount());
        $this->assertCount(0, $order->getItems());
    }

    public function testSetAndGetUser(): void
    {
        $order = new Order();
        $user = new User();

        $order->setUser($user);
        $this->assertSame($user, $order->getUser());
    }

    public function testAddAndRemoveItem(): void
    {
        $order = new Order();
        $item = new OrderItem();

        // Test adding item
        $order->addItem($item);

        $this->assertCount(1, $order->getItems());
        $this->assertTrue($order->getItems()->contains($item));
        $this->assertSame($order, $item->getOrderRef());

        // Test removing item
        $order->removeItem($item);

        $this->assertCount(0, $order->getItems());
        $this->assertFalse($order->getItems()->contains($item));
        $this->assertNull($item->getOrderRef());
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
