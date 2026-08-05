<?php

namespace App\Tests\Functional;

use App\Client\PaymentClient;
use App\Entity\Order;

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
            'shippingAddress' => [
                'firstName' => 'Ada', 'lastName' => 'Lovelace', 'address' => '1 Analytical St',
                'city' => 'Kyiv', 'country' => 'UA', 'postcode' => '01001', 'phone' => '+380001112233',
            ],
        ];
    }

    /**
     * Replaces payment-service with a stub so no HTTP call is made.
     */
    private function stubPayment(): void
    {
        $stub = new class extends PaymentClient {
            public function __construct()
            {
            }

            public function createSession(string $orderId, array $lineItems, string $successUrl, string $cancelUrl): array
            {
                return ['sessionId' => 'cs_test_123', 'url' => 'https://stripe.test/pay/cs_test_123'];
            }
        };

        static::getContainer()->set(PaymentClient::class, $stub);
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

    public function testCheckoutRequiresShippingAddress(): void
    {
        $body = $this->validBody();
        unset($body['shippingAddress']);

        $this->send('POST', '/api/checkout', $body, $this->serviceToken());

        self::assertResponseStatusCodeSame(400);
    }

    public function testCheckoutCreatesPendingOrderAndReturnsUrl(): void
    {
        $this->stubPayment();

        $this->send('POST', '/api/checkout', $this->validBody(), $this->serviceToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('https://stripe.test/pay/cs_test_123', $body['url']);

        $order = $this->em->getRepository(Order::class)->find(\Symfony\Component\Uid\Uuid::fromString($body['orderId']));
        self::assertNotNull($order);
        self::assertSame('PENDING', $order->getStatus());
        self::assertSame('cs_test_123', $order->getStripeSessionId());
        self::assertSame(3998, $order->getTotalAmount());
        self::assertSame('Kyiv', $order->getShippingAddress()['city'] ?? null);

        $outbox = $this->em->getRepository(\App\Entity\OutboxMessage::class)->findAll();
        self::assertCount(1, $outbox);
        self::assertSame('OrderCreated', $outbox[0]->getEventName());
    }

    public function testCheckoutFailsOrderWhenPaymentUnavailable(): void
    {
        $failing = new class extends PaymentClient {
            public function __construct()
            {
            }

            public function createSession(string $orderId, array $lineItems, string $successUrl, string $cancelUrl): array
            {
                throw new \RuntimeException('payment-service down');
            }
        };
        static::getContainer()->set(PaymentClient::class, $failing);

        $this->send('POST', '/api/checkout', $this->validBody(), $this->serviceToken());

        self::assertResponseStatusCodeSame(502);

        // The order is created then marked FAILED, and OrderCancelled releases the
        // HELD reservation (OrderCreated + OrderCancelled both in the outbox).
        $events = array_map(
            fn (\App\Entity\OutboxMessage $m) => $m->getEventName(),
            $this->em->getRepository(\App\Entity\OutboxMessage::class)->findAll()
        );
        self::assertContains('OrderCreated', $events);
        self::assertContains('OrderCancelled', $events);
    }
}
