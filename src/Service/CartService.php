<?php

namespace App\Service;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private ProductRepository $productRepository,
        private CartRepository $cartRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * Добавить товар в корзину.
     */
    public function add(int $productId, int $quantity = 1): void
    {
        $user = $this->security->getUser();

        if ($user) {
            // Для залогиненных — БД
            $this->addToDatabase($user, $productId, $quantity);
        } else {
            // Для гостей — сессия
            $this->addToSession($productId, $quantity);
        }
    }

    /**
     * Удалить товар из корзины.
     */
    public function remove(int $productId): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->removeFromDatabase($user, $productId);
        } else {
            $this->removeFromSession($productId);
        }
    }

    /**
     * Обновить количество.
     */
    public function update(int $productId, int $quantity): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->updateInDatabase($user, $productId, $quantity);
        } else {
            $this->updateInSession($productId, $quantity);
        }
    }

    /**
     * Очистить корзину.
     */
    public function clear(): void
    {
        $user = $this->security->getUser();

        if ($user) {
            $this->clearDatabase($user);
        } else {
            $this->clearSession();
        }
    }

    /**
     * Получить содержимое корзины.
     */
    public function getCart(): array
    {
        $user = $this->security->getUser();

        if ($user) {
            return $this->getCartFromDatabase($user);
        }

        return $this->getCartFromSession();
    }

    /**
     * Перенести корзину из сессии в БД (при входе).
     */
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

        $products = $this->productRepository->findBy(['id' => array_keys($sessionCart)]);
        $productMap = [];
        foreach ($products as $product) {
            $productMap[$product->getId()] = $product;
        }

        foreach ($sessionCart as $productId => $quantity) {
            if (isset($productMap[$productId])) {
                $this->addProductToDatabase($user, $productMap[$productId], $quantity);
            }
        }

        $this->clearSession();
    }

    // === Методы для работы с БД ===

    private function addToDatabase($user, int $productId, int $quantity): void
    {
        $cart = $this->getOrCreateCart($user);
        $product = $this->productRepository->find($productId);

        if (!$product) {
            return;
        }

        $this->upsertCartItem($cart, $product, $quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function addProductToDatabase($user, Product $product, int $quantity): void
    {
        $cart = $this->getOrCreateCart($user);
        $this->upsertCartItem($cart, $product, $quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function upsertCartItem(Cart $cart, Product $product, int $quantity): void
    {
        $existingItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProductId() === $product->getId()) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setProductId($product->getId());
            $cartItem->setProductName($product->getName());
            $cartItem->setPrice($product->getPrice());
            $cartItem->setQuantity($quantity);
            $cart->addItem($cartItem);
        }
    }

    private function removeFromDatabase($user, int $productId): void
    {
        $cart = $this->cartRepository->findOneByUser($user);

        if (!$cart) {
            return;
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getProductId() === $productId) {
                $cart->removeItem($item);
                break;
            }
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function updateInDatabase($user, int $productId, int $quantity): void
    {
        $cart = $this->cartRepository->findOneByUser($user);

        if (!$cart) {
            return;
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getProductId() === $productId) {
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
        $cart = $this->cartRepository->findOneByUser($user);

        if ($cart) {
            $cart->clear();
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    private function getCartFromDatabase($user): array
    {
        $cart = $this->cartRepository->findOneByUser($user);

        if (!$cart) {
            return [
                'items' => [],
                'total' => 0,
                'count' => 0,
            ];
        }

        $productIds = [];
        foreach ($cart->getItems() as $item) {
            $productIds[] = $item->getProductId();
        }

        $productMap = [];
        foreach ($this->productRepository->findBy(['id' => $productIds]) as $product) {
            $productMap[$product->getId()] = $product;
        }

        $cartItems = [];
        $total = 0;

        foreach ($cart->getItems() as $item) {
            $product = $productMap[$item->getProductId()] ?? null;
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
        $cart = $this->cartRepository->findOneByUser($user);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }

    // === Методы для работы с сессией ===

    private function addToSession(int $productId, int $quantity): void
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

    private function removeFromSession(int $productId): void
    {
        $session = $this->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        unset($cart[$productId]);
        $session->set(self::CART_SESSION_KEY, $cart);
    }

    private function updateInSession(int $productId, int $quantity): void
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

        $products = $this->productRepository->findBy(['id' => array_keys($cart)]);
        $productMap = [];
        foreach ($products as $product) {
            $productMap[$product->getId()] = $product;
        }

        $cartItems = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productMap[$productId] ?? null;

            if ($product && $product->getStock() >= $quantity) {
                $itemTotal = $product->getPrice() * $quantity;
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
