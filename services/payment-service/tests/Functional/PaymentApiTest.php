<?php

namespace App\Tests\Functional;

use App\Entity\Payment;
use App\Service\StripeCheckoutService;
use Symfony\Component\Uid\Uuid;

class PaymentApiTest extends ApiTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validBody(?string $orderId = null): array
    {
        return [
            'orderId' => $orderId ?? (string) Uuid::v4(),
            'successUrl' => 'https://shop.test/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancelUrl' => 'https://shop.test/checkout/cancel',
            'lineItems' => [
                ['name' => 'Ink', 'price' => 1999, 'quantity' => 2],
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

            public function createSession(string $orderId, array $lineItems, string $successUrl, string $cancelUrl): array
            {
                return ['id' => 'cs_test_123', 'url' => 'https://stripe.test/pay/cs_test_123'];
            }
        };

        static::getContainer()->set(StripeCheckoutService::class, $stub);
    }

    public function testCreateRequiresAuth(): void
    {
        $this->send('POST', '/api/payments', $this->validBody());

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateForbiddenWithoutPaymentAdmin(): void
    {
        $this->send('POST', '/api/payments', $this->validBody(), $this->serviceToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateValidatesTopLevelFields(): void
    {
        $this->send('POST', '/api/payments', ['orderId' => 'not-a-uuid'], $this->paymentAdminToken());

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateRejectsEmptyLineItems(): void
    {
        $body = $this->validBody();
        $body['lineItems'] = [];

        $this->send('POST', '/api/payments', $body, $this->paymentAdminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateRejectsInvalidLineItem(): void
    {
        $body = $this->validBody();
        $body['lineItems'] = [['name' => '', 'price' => 10, 'quantity' => 0]];

        $this->send('POST', '/api/payments', $body, $this->paymentAdminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatePersistsPaymentAndReturnsUrl(): void
    {
        $this->stubStripe();

        $this->send('POST', '/api/payments', $this->validBody(), $this->paymentAdminToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('https://stripe.test/pay/cs_test_123', $body['url']);
        self::assertSame('cs_test_123', $body['sessionId']);

        $payment = $this->em->getRepository(Payment::class)->find(Uuid::fromString($body['paymentId']));
        self::assertNotNull($payment);
        self::assertSame(Payment::STATUS_INITIATED, $payment->getStatus());
        self::assertSame('cs_test_123', $payment->getStripeSessionId());
        self::assertSame(3998, $payment->getAmount());
    }

    public function testCreateIsIdempotentPerOrder(): void
    {
        $this->stubStripe();
        $orderId = (string) Uuid::v4();

        $this->send('POST', '/api/payments', $this->validBody($orderId), $this->paymentAdminToken());
        self::assertResponseStatusCodeSame(201);
        $first = $this->json()['paymentId'];

        $this->send('POST', '/api/payments', $this->validBody($orderId), $this->paymentAdminToken());
        self::assertResponseStatusCodeSame(201);
        $second = $this->json()['paymentId'];

        self::assertSame($first, $second, 'Same order must reuse the same payment row');
        self::assertCount(1, $this->em->getRepository(Payment::class)->findAll());
    }
}
