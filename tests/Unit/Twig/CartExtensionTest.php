<?php

namespace App\Tests\Unit\Twig;

use App\Service\CartService;
use App\Twig\CartExtension;
use PHPUnit\Framework\TestCase;

class CartExtensionTest extends TestCase
{
    private CartExtension $extension;
    private $cartService;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->extension = new CartExtension($this->cartService);
    }

    public function testGetFunctionsReturnsCorrectFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertEquals('get_cart_count', $functions[0]->getName());
        $this->assertEquals('get_cart_total', $functions[1]->getName());
    }

    public function testGetCartCountCallsService(): void
    {
        $this->cartService
            ->expects($this->once())
            ->method('getCount')
            ->willReturn(5);

        $this->assertEquals(5, $this->extension->getCartCount());
    }

    public function testGetCartTotalCallsService(): void
    {
        $this->cartService
            ->expects($this->once())
            ->method('getCart')
            ->willReturn(['items' => [], 'total' => 15000, 'count' => 2]);

        $this->assertEquals(15000, $this->extension->getCartTotal());
    }
}