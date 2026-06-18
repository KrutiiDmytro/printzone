<?php

namespace App\Service;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Client\CatalogClient;
use App\Catalog\View\ProductView;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private CatalogClient $catalog,
        private CartRepository $cartRepository,
        private EntityManagerInterface $entityManager,
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
            $this->removeFromDatabase($user, $productId);
        } else {
            $this->removeFromSession($productId);
        }
    }

    public function update(string $productId, int $quantity): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->updateInDatabase($user, $productId, $quantity);
        } else {
            $this->updateInSession($productId, $quantity);
        }
    }

    public function clear(): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->clearDatabase($user);
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
            if (isset($productMap[(string) $productId])) {
                $this->addProductToDatabase($user, $productMap[(string) $productId], $quantity);
            }
        }

        $this->clearSession();
    }

    // === Database ===

    private function addToDatabase($user, string $productId, int $quantity): void
    {
        $product = $this->catalog->product($productId);

        if (null === $product) {
            return;
        }

        $cart = $this->getOrCreateCart($user);
        $this->upsertCartItem($cart, $product, $quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function addProductToDatabase($user, ProductView $product, int $quantity): void
    {
        $cart = $this->getOrCreateCart($user);
        $this->upsertCartItem($cart, $product, $quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function upsertCartItem(Cart $cart, ProductView $product, int $quantity): void
    {
        $existingItem = null;
        foreach ($cart->getItems() as $item) {
            if ((string) $item->getProductId() === $product->getId()) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setProductId(Uuid::fromString($product->getId()));
            $cartItem->setProductName((string) $product->getName());
            $cartItem->setPrice((int) $product->getPrice());
            $cartItem->setQuantity($quantity);
            $cart->addItem($cartItem);
        }
    }

    private function removeFromDatabase($user, string $productId): void
    {
        $cart = $this->cartRepository->findOneByUserId($user->getId());

        if (!$cart) {
            return;
        }

        foreach ($cart->getItems() as $item) {
            if ((string) $item->getProductId() === $productId) {
                $cart->removeItem($item);
                break;
            }
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function updateInDatabase($user, string $productId, int $quantity): void
    {
        $cart = $this->cartRepository->findOneByUserId($user->getId());

        if (!$cart) {
            return;
        }

        foreach ($cart->getItems() as $item) {
            if ((string) $item->getProductId() === $productId) {
                if ($quantity <= 0) {
                    $cart->removeItem($item);
                } else {
                    $item->setQuantity($quantity);
                }
                break;
            }
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function clearDatabase($user): void
    {
        $cart = $this->cartRepository->findOneByUserId($user->getId());

        if ($cart) {
            $cart->clear();
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    private function getCartFromDatabase($user): array
    {
        $cart = $this->cartRepository->findOneByUserId($user->getId());

        if (!$cart) {
            return ['items' => [], 'total' => 0, 'count' => 0];
        }

        $productIds = [];
        foreach ($cart->getItems() as $item) {
            $productIds[] = (string) $item->getProductId();
        }

        $productMap = $this->catalog->productsByIds($productIds);

        $cartItems = [];
        $total = 0;

        foreach ($cart->getItems() as $item) {
            $product = $productMap[(string) $item->getProductId()] ?? null;
            if (null === $product) {
                // Product no longer exists in Catalog — skip the orphaned line.
                continue;
            }

            $cartItems[] = [
                'product' => $product,
                'quantity' => $item->getQuantity(),
                'total' => $item->getTotal(),
            ];
            $total += $item->getTotal();
        }

        return [
            'items' => $cartItems,
            'total' => $total,
            'count' => count($cartItems),
        ];
    }

    private function getOrCreateCart($user): Cart
    {
        $cart = $this->cartRepository->findOneByUserId($user->getId());

        if (!$cart) {
            $cart = new Cart();
            $cart->setUserId($user->getId());
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }

    // === Session ===

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
