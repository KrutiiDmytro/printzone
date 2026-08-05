<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Messaging\Domain\IntegrationEvent;
use App\Notification\EmailChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The heart of the notification-service: consumes integration events off the
 * broker and turns each one into a customer notification. Today only order.OrderPaid
 * (→ payment receipt) is wired — the one event that carries a recipient email
 * without a cross-service lookup. Unknown events are a deliberate no-op.
 *
 * Delivery is at-least-once; a replayed OrderPaid re-sends the receipt. That is
 * accepted for MVP (a duplicate receipt is harmless, not a state change).
 */
#[AsMessageHandler]
final class NotificationHandler
{
    public function __construct(
        private readonly EmailChannel $email,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        if ('order' === $event->aggregate && 'OrderPaid' === $event->eventName) {
            $this->onOrderPaid($event->payload);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onOrderPaid(array $payload): void
    {
        $to = $payload['userEmail'] ?? null;
        if (!\is_string($to) || '' === $to) {
            $this->logger->warning('OrderPaid without a usable userEmail; skipping receipt', [
                'orderId' => $payload['orderId'] ?? null,
            ]);

            return;
        }

        $this->email->send($to, 'Дякуємо! Ваше замовлення оплачено', 'email/order_paid.html.twig', [
            'orderId' => $payload['orderId'] ?? null,
            'totalAmount' => $payload['totalAmount'] ?? 0,
        ]);

        $this->logger->info('OrderPaid receipt sent', ['to' => $to, 'orderId' => $payload['orderId'] ?? null]);
    }
}
