<?php

namespace App\Tests\Unit\Cart\Domain\Entity;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
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

    private function makeItem(int $productId, string $name, int $price, int $quantity): CartItem
    {
        $item = new CartItem();
        $item->setProductId($productId);
        $item->setProductName($name);
        $item->setPrice($price);
        $item->setQuantity($quantity);

        return $item;
    }

    public function testGetTotalReturnsZeroWhenEmpty(): void
    {
        $this->assertEquals(0, $this->cart->getTotal());
    }

    public function testGetTotalCalculatesCorrectly(): void
    {
        $this->cart->addItem($this->makeItem(1, 'Product 1', 10000, 2));
        $this->cart->addItem($this->makeItem(2, 'Product 2', 5000, 3));

        // 2 * 10000 + 3 * 5000 = 20000 + 15000 = 35000
        $this->assertEquals(35000, $this->cart->getTotal());
    }

    public function testAddItemAddsItemToCart(): void
    {
        $item = $this->makeItem(1, 'Product', 10000, 1);

        $this->cart->addItem($item);

        $this->assertCount(1, $this->cart->getItems());
        $this->assertTrue($this->cart->getItems()->contains($item));
        $this->assertEquals($this->cart, $item->getCart());
    }

    public function testAddItemDoesNotAddDuplicate(): void
    {
        $item = $this->makeItem(1, 'Product', 10000, 1);

        $this->cart->addItem($item);
        $this->cart->addItem($item);

        $this->assertCount(1, $this->cart->getItems());
    }

    public function testRemoveItemRemovesItemFromCart(): void
    {
        $item = $this->makeItem(1, 'Product', 10000, 1);

        $this->cart->addItem($item);
        $this->cart->removeItem($item);

        $this->assertCount(0, $this->cart->getItems());
        $this->assertNull($item->getCart());
    }

    public function testClearRemovesAllItems(): void
    {
        $this->cart->addItem($this->makeItem(1, 'Product 1', 10000, 1));
        $this->cart->addItem($this->makeItem(2, 'Product 2', 5000, 1));

        $this->cart->clear();

        $this->assertCount(0, $this->cart->getItems());
        $this->assertEquals(0, $this->cart->getTotal());
    }
}
