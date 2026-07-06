<?php

namespace App\Tests\Functional;

use App\Entity\OutboxMessage;
use App\Entity\Payment;
use Symfony\Component\Uid\Uuid;

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

    private function event(string $type, string $orderId): string
    {
        return (string) json_encode([
            'id' => 'evt_'.$type,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => ['metadata' => ['order_id' => $orderId]]],
        ]);
    }

    /**
     * @return string[]
     */
    private function outboxEventNames(): array
    {
        return array_map(
            fn (OutboxMessage $m) => $m->getEventName(),
            $this->em->getRepository(OutboxMessage::class)->findAll()
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

    public function testCompletedSessionMarksSucceededAndEmitsEvent(): void
    {
        $orderId = Uuid::v4();
        $this->seedPayment(Payment::STATUS_INITIATED, $orderId);

        $this->postEvent($this->event('checkout.session.completed', (string) $orderId));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $payment = $this->em->getRepository(Payment::class)->findOneBy(['orderId' => $orderId]);
        self::assertSame(Payment::STATUS_SUCCEEDED, $payment->getStatus());
        self::assertContains('PaymentSucceeded', $this->outboxEventNames());
    }

    public function testPaymentFailedMarksFailedAndEmitsEvent(): void
    {
        $orderId = Uuid::v4();
        $this->seedPayment(Payment::STATUS_INITIATED, $orderId);

        $this->postEvent($this->event('payment_intent.payment_failed', (string) $orderId));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $payment = $this->em->getRepository(Payment::class)->findOneBy(['orderId' => $orderId]);
        self::assertSame(Payment::STATUS_FAILED, $payment->getStatus());
        self::assertContains('PaymentFailed', $this->outboxEventNames());
    }

    public function testReplayedWebhookIsIdempotent(): void
    {
        $orderId = Uuid::v4();
        $this->seedPayment(Payment::STATUS_SUCCEEDED, $orderId);

        $this->postEvent($this->event('checkout.session.completed', (string) $orderId));

        self::assertResponseIsSuccessful();

        // Already resolved → no transition, no duplicate event.
        self::assertCount(0, $this->em->getRepository(OutboxMessage::class)->findAll());
    }
}
