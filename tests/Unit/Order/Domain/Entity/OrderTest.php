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
}
