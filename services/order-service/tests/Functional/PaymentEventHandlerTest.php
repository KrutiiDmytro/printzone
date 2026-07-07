<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OutboxMessage;
use App\Messaging\Domain\IntegrationEvent;
use App\MessageHandler\PaymentEventHandler;

class PaymentEventHandlerTest extends ApiTestCase
{
    private function handle(string $eventName, string $orderId): void
    {
        $handler = static::getContainer()->get(PaymentEventHandler::class);
        $handler(new IntegrationEvent('payment', $eventName, ['orderId' => $orderId]));
    }

    /**
     * @return string[]
     */
    private function outboxEventNames(): array
    {
        $this->em->clear();

        return array_map(
            fn (OutboxMessage $m) => $m->getEventName(),
            $this->em->getRepository(OutboxMessage::class)->findAll()
        );
    }

    public function testPaymentSucceededMarksOrderPaidAndEmitsOrderPaid(): void
    {
        $order = $this->seedOrder('PENDING');

        $this->handle('PaymentSucceeded', (string) $order->getId());

        self::assertSame('PAID', $this->em->getRepository(Order::class)->find($order->getId())->getStatus());
        self::assertContains('OrderPaid', $this->outboxEventNames());

        // OrderPaid carries the delivery snapshot so delivery-service can ship it.
        $paid = $this->em->getRepository(OutboxMessage::class)->findOneBy(['eventName' => 'OrderPaid']);
        self::assertSame('Kyiv', $paid->getPayload()['shippingAddress']['city'] ?? null);
    }

    public function testPaymentFailedMarksOrderFailedAndEmitsOrderCancelled(): void
    {
        $order = $this->seedOrder('PENDING');

        $this->handle('PaymentFailed', (string) $order->getId());

        self::assertSame('FAILED', $this->em->getRepository(Order::class)->find($order->getId())->getStatus());
        self::assertContains('OrderCancelled', $this->outboxEventNames());
    }

    public function testReplayIsIdempotent(): void
    {
        $order = $this->seedOrder('PAID');

        $this->handle('PaymentSucceeded', (string) $order->getId());

        // Already resolved → no transition, no duplicate OrderPaid.
        self::assertCount(0, $this->em->getRepository(OutboxMessage::class)->findAll());
        self::assertSame('PAID', $this->em->getRepository(Order::class)->find($order->getId())->getStatus());
    }

    public function testUnknownOrderIsNoop(): void
    {
        $this->handle('PaymentSucceeded', (string) \Symfony\Component\Uid\Uuid::v4());

        self::assertCount(0, $this->em->getRepository(OutboxMessage::class)->findAll());
    }
}
