<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class OrderTest extends TestCase
{
    private function makeItem(): OrderItem
    {
        $item = new OrderItem();
        $item->setProductId(Uuid::v4());
        $item->setProductName('Product');
        $item->setQuantity(2);
        $item->setPrice(10000);

        return $item;
    }

    public function testAddItemAddsItemToOrder(): void
    {
        $order = new Order();
        $item = $this->makeItem();

        $order->addItem($item);

        $this->assertCount(1, $order->getItems());
        $this->assertTrue($order->getItems()->contains($item));
        $this->assertSame($order, $item->getOrderRef());
    }

    public function testRemoveItemRemovesItemFromOrder(): void
    {
        $order = new Order();
        $item = $this->makeItem();

        $order->addItem($item);
        $order->removeItem($item);

        $this->assertCount(0, $order->getItems());
        $this->assertNull($item->getOrderRef());
    }

    public function testOrderInitialization(): void
    {
        $order = new Order();

        $this->assertInstanceOf(\DateTimeInterface::class, $order->getCreatedAt());
        $this->assertSame('PENDING', $order->getStatus());
        $this->assertSame(0, $order->getTotalAmount());
        $this->assertCount(0, $order->getItems());
    }

    public function testSetAndGetUserId(): void
    {
        $order = new Order();
        $userId = Uuid::v4();

        $order->setUserId($userId);

        $this->assertSame($userId, $order->getUserId());
    }

    public function testToEventItemsSnapshotsLines(): void
    {
        $order = new Order();
        $order->addItem($this->makeItem());

        $events = $order->toEventItems();

        $this->assertCount(1, $events);
        $this->assertArrayHasKey('productId', $events[0]);
        $this->assertSame(2, $events[0]['quantity']);
    }

    public function testStatusChange(): void
    {
        $order = new Order();
        $order->setStatus('SHIPPED');

        $this->assertSame('SHIPPED', $order->getStatus());
    }
}
