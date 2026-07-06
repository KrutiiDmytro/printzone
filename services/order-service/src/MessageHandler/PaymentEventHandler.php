<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Order;
use App\Messaging\Application\OutboxRecorder;
use App\Messaging\Domain\IntegrationEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Order side of the checkout Saga. Consumes payment-service events and drives the
 * order status (this replaces the old in-house Stripe webhook):
 *   PaymentSucceeded → Order PAID   + emit OrderPaid      (Catalog commits stock)
 *   PaymentFailed    → Order FAILED + emit OrderCancelled (Catalog releases HELD)
 *
 * Delivery is at-least-once, so both branches are idempotent: only a PENDING
 * order transitions, and OrderPaid/OrderCancelled commit atomically with the
 * status change via the transactional outbox (relay ships them to RabbitMQ).
 */
#[AsMessageHandler]
final class PaymentEventHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $em,
        private readonly OutboxRecorder $outboxRecorder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        if ('payment' !== $event->aggregate) {
            return;
        }

        match ($event->eventName) {
            'PaymentSucceeded' => $this->onSucceeded($event->payload),
            'PaymentFailed' => $this->onFailed($event->payload),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onSucceeded(array $payload): void
    {
        $order = $this->pendingOrder($payload);
        if (null === $order) {
            return;
        }

        $order->setStatus('PAID');
        $this->outboxRecorder->record('order', 'OrderPaid', [
            'orderId' => (string) $order->getId(),
            'userId' => (string) $order->getUserId(),
            'userEmail' => $order->getUserEmail(),
            'totalAmount' => $order->getTotalAmount(),
            'paidAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $this->em->flush();

        $this->logger->info('Order marked PAID from PaymentSucceeded', ['orderId' => (string) $order->getId()]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onFailed(array $payload): void
    {
        $order = $this->pendingOrder($payload);
        if (null === $order) {
            return;
        }

        $order->setStatus('FAILED');
        $this->outboxRecorder->record('order', 'OrderCancelled', [
            'orderId' => (string) $order->getId(),
            'items' => $order->toEventItems(),
        ]);
        $this->em->flush();

        $this->logger->info('Order marked FAILED from PaymentFailed', ['orderId' => (string) $order->getId()]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function pendingOrder(array $payload): ?Order
    {
        $orderId = $payload['orderId'] ?? null;
        if (!\is_string($orderId) || !Uuid::isValid($orderId)) {
            $this->logger->warning('Payment event: invalid orderId', ['orderId' => $orderId]);

            return null;
        }

        $order = $this->orders->find(Uuid::fromString($orderId));
        if (null === $order) {
            $this->logger->warning('Payment event: order not found', ['orderId' => $orderId]);

            return null;
        }

        // Idempotent: a replayed event or an already-resolved order is a no-op,
        // so no duplicate OrderPaid/OrderCancelled is ever emitted.
        if ('PENDING' !== $order->getStatus()) {
            $this->logger->info('Payment event: order already resolved', [
                'orderId' => $orderId,
                'status' => $order->getStatus(),
            ]);

            return null;
        }

        return $order;
    }
}
