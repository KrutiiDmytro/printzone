<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\Messaging\Domain\IntegrationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes integration events delivered from RabbitMQ. Stands in for the future
 * Notification service: on OrderPaid it emails the customer a receipt.
 */
#[AsMessageHandler]
final class IntegrationEventHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%admin.email%')]
        private readonly string $fromEmail,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        $this->logger->info('Integration event received', [
            'event' => $event->eventName,
            'aggregate' => $event->aggregate,
            'traceId' => $event->traceId,
        ]);

        if ('OrderPaid' === $event->eventName) {
            $this->handleOrderPaid($event->payload);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleOrderPaid(array $payload): void
    {
        $to = $payload['userEmail'] ?? null;
        if (!is_string($to) || '' === $to) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromEmail)
            ->to($to)
            ->subject('Дякуємо! Ваше замовлення оплачено')
            ->htmlTemplate('email/order_paid.html.twig')
            ->context([
                'orderId' => $payload['orderId'] ?? null,
                'totalAmount' => $payload['totalAmount'] ?? 0,
            ]);

        $this->mailer->send($email);
    }
}
