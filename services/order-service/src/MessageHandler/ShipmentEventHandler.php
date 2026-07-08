<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Order;
use App\Messaging\Domain\IntegrationEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Advances the order fulfilment lifecycle from delivery-service events:
 *   ShipmentCreated   → PAID     → SHIPPED
 *   ShipmentDelivered → SHIPPED  → DELIVERED
 *
 * Delivery is at-least-once, so transitions are forward-only and idempotent: an
 * order only moves to the next state from the expected prior one, so a replayed
 * event (or events arriving out of order) never moves the order backwards.
 */
#[AsMessageHandler]
final class ShipmentEventHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        if ('shipment' !== $event->aggregate) {
            return;
        }

        [$from, $to] = match ($event->eventName) {
            'ShipmentCreated' => [['PAID'], 'SHIPPED'],
            'ShipmentDelivered' => [['PAID', 'SHIPPED'], 'DELIVERED'],
            default => [null, null],
        };

        if (null === $to) {
            return;
        }

        $order = $this->resolveOrder($event->payload);
        if (null === $order) {
            return;
        }

        if (!in_array($order->getStatus(), $from, true)) {
            $this->logger->info('Shipment event: order not in a transitionable state', [
                'orderId' => (string) $order->getId(),
                'status' => $order->getStatus(),
                'event' => $event->eventName,
            ]);

            return;
        }

        $order->setStatus($to);
        $this->em->flush();

        $this->logger->info('Order advanced from shipment event', [
            'orderId' => (string) $order->getId(),
            'status' => $to,
            'event' => $event->eventName,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveOrder(array $payload): ?Order
    {
        $orderId = $payload['orderId'] ?? null;
        if (!\is_string($orderId) || !Uuid::isValid($orderId)) {
            $this->logger->warning('Shipment event: invalid orderId', ['orderId' => $orderId]);

            return null;
        }

        $order = $this->orders->find(Uuid::fromString($orderId));
        if (null === $order) {
            $this->logger->warning('Shipment event: order not found', ['orderId' => $orderId]);
        }

        return $order;
    }
}
