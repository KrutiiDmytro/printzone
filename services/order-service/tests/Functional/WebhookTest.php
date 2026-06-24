<?php

namespace App\Tests\Functional;

use App\Entity\OutboxMessage;

class WebhookTest extends ApiTestCase
{
    private function postEvent(string $payload): void
    {
        $this->client->request(
            'POST',
            '/stripe/webhook',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload)],
            $payload
        );
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $this->client->request(
            'POST',
            '/stripe/webhook',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef'],
            '{"type":"checkout.session.completed"}'
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testCompletedSessionMarksOrderPaidAndEmitsEvent(): void
    {
        $order = $this->seedOrder('PENDING');

        $payload = json_encode([
            'id' => 'evt_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => ['order_id' => (string) $order->getId()]]],
        ]);

        $this->postEvent($payload);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(\App\Entity\Order::class)->find($order->getId());
        self::assertSame('PAID', $reloaded->getStatus());

        $events = array_map(fn (OutboxMessage $m) => $m->getEventName(), $this->em->getRepository(OutboxMessage::class)->findAll());
        self::assertContains('OrderPaid', $events);
    }

    public function testPaymentFailedMarksOrderFailed(): void
    {
        $order = $this->seedOrder('PENDING');

        $payload = json_encode([
            'id' => 'evt_2',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['metadata' => ['order_id' => (string) $order->getId()]]],
        ]);

        $this->postEvent($payload);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(\App\Entity\Order::class)->find($order->getId());
        self::assertSame('FAILED', $reloaded->getStatus());
    }
}
