<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\StockReservation;
use App\Messaging\Domain\IntegrationEvent;
use App\Repository\ProductRepository;
use App\Repository\StockReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Choreographed checkout Saga, Catalog side. Consumes the monolith's Order
 * events and drives stock reservations:
 *   OrderCreated   → reserve HELD (one row per item, idempotent)
 *   OrderPaid      → COMMITTED + decrement products.stock
 *   OrderCancelled → RELEASED (held lines only)
 *
 * Delivery is at-least-once, so every branch is idempotent: re-delivery acts
 * only on rows still in the expected source state.
 */
#[AsMessageHandler]
final class OrderEventHandler
{
    public function __construct(
        private readonly StockReservationRepository $reservations,
        private readonly ProductRepository $products,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        if ('order' !== $event->aggregate) {
            return;
        }

        match ($event->eventName) {
            'OrderCreated' => $this->onOrderCreated($event->payload),
            'OrderPaid' => $this->onOrderPaid($event->payload),
            'OrderCancelled' => $this->onOrderCancelled($event->payload),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onOrderCreated(array $payload): void
    {
        $orderId = $this->uuid($payload['orderId'] ?? null);
        if (null === $orderId) {
            return;
        }

        foreach ($this->items($payload) as [$productId, $quantity]) {
            // Idempotent: the (order_id, product_id) unique key already holds this line.
            if (null !== $this->reservations->findOneByOrderAndProduct($orderId, $productId)) {
                continue;
            }
            $this->em->persist(new StockReservation($orderId, $productId, $quantity));
        }

        $this->em->flush();
        $this->logger->info('Saga: stock reserved (HELD)', ['orderId' => (string) $orderId]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onOrderPaid(array $payload): void
    {
        $orderId = $this->uuid($payload['orderId'] ?? null);
        if (null === $orderId) {
            return;
        }

        foreach ($this->reservations->findByOrder($orderId) as $reservation) {
            // Idempotent: only HELD lines move to COMMITTED, so stock drops once.
            if (StockReservation::STATUS_HELD !== $reservation->getStatus()) {
                continue;
            }
            $reservation->setStatus(StockReservation::STATUS_COMMITTED);

            $product = $this->products->find($reservation->getProductId());
            if (null !== $product) {
                $product->setStock(max(0, $product->getStock() - $reservation->getQuantity()));
            }
        }

        $this->em->flush();
        $this->logger->info('Saga: stock committed', ['orderId' => (string) $orderId]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onOrderCancelled(array $payload): void
    {
        $orderId = $this->uuid($payload['orderId'] ?? null);
        if (null === $orderId) {
            return;
        }

        foreach ($this->reservations->findByOrder($orderId) as $reservation) {
            // Release only still-held lines; committed stock is not restored here.
            if (StockReservation::STATUS_HELD !== $reservation->getStatus()) {
                continue;
            }
            $reservation->setStatus(StockReservation::STATUS_RELEASED);
        }

        $this->em->flush();
        $this->logger->info('Saga: stock released', ['orderId' => (string) $orderId]);
    }

    private function uuid(mixed $value): ?Uuid
    {
        return \is_string($value) && Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return iterable<array{0: Uuid, 1: int}>
     */
    private function items(array $payload): iterable
    {
        $items = $payload['items'] ?? [];
        if (!\is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $productId = $this->uuid($item['productId'] ?? null);
            $quantity = (int) ($item['quantity'] ?? 0);
            if (null !== $productId && $quantity > 0) {
                yield [$productId, $quantity];
            }
        }
    }
}
