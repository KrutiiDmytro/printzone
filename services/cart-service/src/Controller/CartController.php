<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Persistent cart for authenticated users. The monolith is the only caller: it
 * resolves products via the Catalog Service and sends snapshots (name + price)
 * here, so this service never talks to Catalog itself.
 */
#[Route('/api/carts')]
class CartController
{
    public function __construct(
        private readonly CartRepository $carts,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/{userId}', name: 'cart_get', methods: ['GET'])]
    public function get(string $userId): JsonResponse
    {
        $uid = $this->parseUuid($userId);
        if (null === $uid) {
            return $this->error('Invalid userId.', Response::HTTP_NOT_FOUND);
        }

        $cart = $this->carts->findOneByUserId($uid);

        return new JsonResponse($this->toArray($cart));
    }

    #[Route('/{userId}/items', name: 'cart_add_item', methods: ['POST'])]
    public function addItem(string $userId, Request $request): JsonResponse
    {
        $uid = $this->parseUuid($userId);
        if (null === $uid) {
            return $this->error('Invalid userId.', Response::HTTP_NOT_FOUND);
        }

        $data = $this->decode($request);
        $productId = $this->parseUuid((string) ($data['productId'] ?? ''));
        $productName = trim((string) ($data['productName'] ?? ''));
        $price = $data['price'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (null === $productId || '' === $productName || !is_int($price) || $price < 0 || !is_int($quantity) || $quantity < 1) {
            return $this->error('Invalid item payload.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cart = $this->carts->findOneByUserId($uid);
        if (null === $cart) {
            $cart = (new Cart())->setUserId($uid);
            $this->em->persist($cart);
        }

        $existing = $this->findItem($cart, $productId);
        if (null !== $existing) {
            $existing->setQuantity($existing->getQuantity() + $quantity);
        } else {
            $item = (new CartItem())
                ->setProductId($productId)
                ->setProductName($productName)
                ->setPrice($price)
                ->setQuantity($quantity);
            $cart->addItem($item);
        }

        $cart->touch();
        $this->em->flush();

        return new JsonResponse($this->toArray($cart));
    }

    #[Route('/{userId}/items/{productId}', name: 'cart_update_item', methods: ['PATCH'])]
    public function updateItem(string $userId, string $productId, Request $request): JsonResponse
    {
        $uid = $this->parseUuid($userId);
        $pid = $this->parseUuid($productId);
        if (null === $uid || null === $pid) {
            return $this->error('Invalid identifier.', Response::HTTP_NOT_FOUND);
        }

        $quantity = $this->decode($request)['quantity'] ?? null;
        if (!is_int($quantity)) {
            return $this->error('Invalid quantity.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cart = $this->carts->findOneByUserId($uid);
        $item = null !== $cart ? $this->findItem($cart, $pid) : null;
        if (null === $cart || null === $item) {
            return $this->error('Item not found.', Response::HTTP_NOT_FOUND);
        }

        if ($quantity <= 0) {
            $cart->removeItem($item);
        } else {
            $item->setQuantity($quantity);
        }

        $cart->touch();
        $this->em->flush();

        return new JsonResponse($this->toArray($cart));
    }

    #[Route('/{userId}/items/{productId}', name: 'cart_remove_item', methods: ['DELETE'])]
    public function removeItem(string $userId, string $productId): Response
    {
        $uid = $this->parseUuid($userId);
        $pid = $this->parseUuid($productId);
        if (null === $uid || null === $pid) {
            return $this->error('Invalid identifier.', Response::HTTP_NOT_FOUND);
        }

        $cart = $this->carts->findOneByUserId($uid);
        $item = null !== $cart ? $this->findItem($cart, $pid) : null;
        if (null !== $cart && null !== $item) {
            $cart->removeItem($item);
            $cart->touch();
            $this->em->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{userId}', name: 'cart_clear', methods: ['DELETE'])]
    public function clear(string $userId): Response
    {
        $uid = $this->parseUuid($userId);
        if (null === $uid) {
            return $this->error('Invalid userId.', Response::HTTP_NOT_FOUND);
        }

        $cart = $this->carts->findOneByUserId($uid);
        if (null !== $cart) {
            $cart->clear();
            $cart->touch();
            $this->em->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function findItem(Cart $cart, Uuid $productId): ?CartItem
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getProductId()->equals($productId)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{items: list<array{productId: string, productName: string, price: int, quantity: int}>}
     */
    private function toArray(?Cart $cart): array
    {
        $items = [];
        if (null !== $cart) {
            foreach ($cart->getItems() as $item) {
                $items[] = [
                    'productId' => $item->getProductId()->toRfc4122(),
                    'productName' => $item->getProductName(),
                    'price' => $item->getPrice(),
                    'quantity' => $item->getQuantity(),
                ];
            }
        }

        return ['items' => $items];
    }

    private function parseUuid(string $value): ?Uuid
    {
        return Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent() ?: '{}', true);

        return is_array($data) ? $data : [];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
