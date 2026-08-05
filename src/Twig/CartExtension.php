<?php

namespace App\Twig;

use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_cart_count', [$this, 'getCartCount']),
            new TwigFunction('get_cart_total', [$this, 'getCartTotal']),
        ];
    }

    public function getCartCount(): int
    {
        return $this->cartService->getCount();
    }

    public function getCartTotal(): int
    {
        $cart = $this->cartService->getCart();

        return $cart['total'];
    }
}
