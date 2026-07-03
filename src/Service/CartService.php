<?php

namespace App\Service;

use App\Cart\Client\CartClient;
use App\Catalog\Client\CatalogClient;
use App\Catalog\View\ProductView;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private CatalogClient $catalog,
        private CartClient $cartClient,
        private Security $security
    ) {
    }

    public function add(string $productId, int $quantity = 1): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->addToDatabase($user, $productId, $quantity);
        } else {
            $this->addToSession($productId, $quantity);
        }
    }

    public function remove(string $productId): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->cartClient->removeItem((string) $user->getId(), $productId);
        } else {
            $this->removeFromSession($productId);
        }
    }

    public function update(string $productId, int $quantity): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->cartClient->updateItem((string) $user->getId(), $productId, $quantity);
        } else {
            $this->updateInSession($productId, $quantity);
        }
    }

    public function clear(): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->cartClient->clear((string) $user->getId());
        } else {
            $this->clearSession();
        }
    }

    public function getCart(): array
    {
        $user = $this->security->getUser();

        if ($user) {
            return $this->getCartFromDatabase($user);
        }

        return $this->getCartFromSession();
    }

    public function migrateSessionToDatabase(): void
    {
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $sessionCart = $this->getSession()->get(self::CART_SESSION_KEY, []);

        if (empty($sessionCart)) {
            return;
        }

        $productMap = $this->catalog->productsByIds(array_map('strval', array_keys($sessionCart)));

        foreach ($sessionCart as $productId => $quantity) {
            $product = $productMap[(string) $productId] ?? null;
            if (null !== $product) {
                $this->addProductToDatabase($user, $product, $quantity);
            }
        }

        $this->clearSession();
    }

    // === Cart Service (authenticated users) ===

    private function addToDatabase($user, string $productId, int $quantity): void
    {
        $product = $this->catalog->product($productId);

        if (null === $product) {
            return;
        }

        $this->addProductToDatabase($user, $product, $quantity);
    }

    private function addProductToDatabase($user, ProductView $product, int $quantity): void
    {
        $this->cartClient->addItem(
            (string) $user->getId(),
            $product->getId(),
            (string) $product->getName(),
            (int) $product->getPrice(),
            $quantity
        );
    }

    private function getCartFromDatabase($user): array
    {
        $items = $this->cartClient->get((string) $user->getId());

        if ([] === $items) {
            return ['items' => [], 'total' => 0, 'count' => 0];
        }

        $productMap = $this->catalog->productsByIds(array_column($items, 'productId'));

        $cartItems = [];
        $total = 0;

        foreach ($items as $item) {
            $product = $productMap[$item['productId']] ?? null;
            if (null === $product) {
                // Product no longer exists in Catalog — skip the orphaned line.
                continue;
            }

            $itemTotal = $item['price'] * $item['quantity'];
            $cartItems[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'total' => $itemTotal,
            ];
            $total += $itemTotal;
        }

        return [
            'items' => $cartItems,
            'total' => $total,
            'count' => count($cartItems),
        ];
    }

    // === Session (guests) ===

    private function addToSession(string $productId, int $quantity): void
    {
        $session = $this->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        if (isset($cart[$productId])) {
            $cart[$productId] += $quantity;
        } else {
            $cart[$productId] = $quantity;
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

    private function removeFromSession(string $productId): void
    {
        $session = $this->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        unset($cart[$productId]);
        $session->set(self::CART_SESSION_KEY, $cart);
    }

    private function updateInSession(string $productId, int $quantity): void
    {
        $session = $this->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

    private function clearSession(): void
    {
        $this->getSession()->remove(self::CART_SESSION_KEY);
    }

    private function getCartFromSession(): array
    {
        $session = $this->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        if (empty($cart)) {
            return ['items' => [], 'total' => 0, 'count' => 0];
        }

        $productMap = $this->catalog->productsByIds(array_map('strval', array_keys($cart)));

        $cartItems = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productMap[(string) $productId] ?? null;

            if ($product && $product->getStock() >= $quantity) {
                $itemTotal = (int) $product->getPrice() * $quantity;
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'total' => $itemTotal,
                ];
                $total += $itemTotal;
            }
        }

        return [
            'items' => $cartItems,
            'total' => $total,
            'count' => count($cartItems),
        ];
    }

    private function getSession()
    {
        return $this->requestStack->getSession();
    }

    public function getCount(): int
    {
        $cart = $this->getCart();

        return $cart['count'];
    }
}
