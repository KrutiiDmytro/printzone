<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Service\StripeCheckoutService;

class CheckoutApiTest extends ApiTestCase
{
    private function validBody(): array
    {
        return [
            'userId' => (string) \Symfony\Component\Uid\Uuid::v4(),
            'userEmail' => 'buyer@example.com',
            'successUrl' => 'https://shop.test/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancelUrl' => 'https://shop.test/checkout/cancel',
            'items' => [
                ['productId' => (string) \Symfony\Component\Uid\Uuid::v4(), 'name' => 'Ink', 'price' => 1999, 'quantity' => 2],
            ],
        ];
    }

    /**
     * Replaces the real Stripe gateway with a stub so no network call is made.
     */
    private function stubStripe(): void
    {
        $stub = new class extends StripeCheckoutService {
            public function __construct()
            {
            }

            public function createSession(Order $order, array $lineItems, string $successUrl, string $cancelUrl): array
            {
                return ['id' => 'cs_test_123', 'url' => 'https://stripe.test/pay/cs_test_123'];
            }
        };

        static::getContainer()->set(StripeCheckoutService::class, $stub);
    }

    public function testCheckoutRequiresAuth(): void
    {
        $this->send('POST', '/api/checkout', $this->validBody());

        self::assertResponseStatusCodeSame(401);
    }

    public function testCheckoutValidatesBody(): void
    {
        $this->send('POST', '/api/checkout', ['userEmail' => 'x@y.z'], $this->serviceToken());

        self::assertResponseStatusCodeSame(400);
    }

    public function testCheckoutCreatesPendingOrderAndReturnsUrl(): void
    {
        $this->stubStripe();

        $this->send('POST', '/api/checkout', $this->validBody(), $this->serviceToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('https://stripe.test/pay/cs_test_123', $body['url']);

        $order = $this->em->getRepository(Order::class)->find(\Symfony\Component\Uid\Uuid::fromString($body['orderId']));
        self::assertNotNull($order);
        self::assertSame('PENDING', $order->getStatus());
        self::assertSame('cs_test_123', $order->getStripeSessionId());
        self::assertSame(3998, $order->getTotalAmount());

        $outbox = $this->em->getRepository(\App\Entity\OutboxMessage::class)->findAll();
        self::assertCount(1, $outbox);
        self::assertSame('OrderCreated', $outbox[0]->getEventName());
    }
}
