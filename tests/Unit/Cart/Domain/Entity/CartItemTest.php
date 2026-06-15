<?php

namespace App\Tests\Unit\Cart\Domain\Entity;

use App\Cart\Domain\Entity\CartItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CartItemTest extends TestCase
{
    public function testGetTotalCalculatesCorrectly(): void
    {
        $item = new CartItem();
        $item->setProductId(Uuid::v4());
        $item->setProductName('Product');
        $item->setPrice(10000); // 100.00 EUR
        $item->setQuantity(3);

        // 3 * 10000 = 30000
        $this->assertEquals(30000, $item->getTotal());
    }

    public function testGetTotalReturnsZeroWhenQuantityIsZero(): void
    {
        $item = new CartItem();
        $item->setProductId(Uuid::v4());
        $item->setProductName('Product');
        $item->setPrice(10000);
        $item->setQuantity(0);

        $this->assertEquals(0, $item->getTotal());
    }
}
