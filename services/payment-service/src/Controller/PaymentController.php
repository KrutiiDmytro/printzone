<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Service\StripeCheckoutService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Create-session entrypoint. order-service POSTs the line items here (S2S JWT,
 * ROLE_PAYMENT_ADMIN); this service owns the Stripe Checkout session and the
 * `payments` row. Success/cancel URLs are the monolith's, so they arrive in the
 * body. The order status is NOT touched here — it transitions later via the
 * PaymentSucceeded/PaymentFailed events emitted from the webhook (step 3).
 */
#[Route('/api/payments')]
class PaymentController
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly StripeCheckoutService $stripe,
    ) {
    }

    #[Route('', name: 'api_payment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $orderId = (string) ($data['orderId'] ?? '');
        $successUrl = (string) ($data['successUrl'] ?? '');
        $cancelUrl = (string) ($data['cancelUrl'] ?? '');
        $lineItems = $data['lineItems'] ?? [];

        if (!Uuid::isValid($orderId) || '' === $successUrl || '' === $cancelUrl) {
            return new JsonResponse(['error' => 'Missing or invalid fields: orderId, successUrl, cancelUrl'], 400);
        }
        if (!is_array($lineItems) || [] === $lineItems) {
            return new JsonResponse(['error' => 'lineItems must be a non-empty array'], 422);
        }

        $normalized = [];
        $amount = 0;
        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                return new JsonResponse(['error' => 'Invalid line item'], 422);
            }
            $name = (string) ($item['name'] ?? '');
            $price = (int) ($item['price'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ('' === $name || $price < 0 || $quantity < 1) {
                return new JsonResponse(['error' => 'Invalid line item: name, price>=0, quantity>=1 required'], 422);
            }
            $normalized[] = ['name' => $name, 'price' => $price, 'quantity' => $quantity];
            $amount += $price * $quantity;
        }

        $orderUuid = Uuid::fromString($orderId);

        // Idempotent per order: reuse the row if the monolith retries checkout for
        // the same order instead of piling up duplicate payments.
        $payment = $this->payments->findOneByOrderId($orderUuid);
        if (null === $payment) {
            $payment = new Payment($orderUuid, $amount);
        } else {
            $payment->setAmount($amount);
            $payment->setStatus(Payment::STATUS_INITIATED);
        }

        try {
            $session = $this->stripe->createSession($orderId, $normalized, $successUrl, $cancelUrl);
        } catch (\Throwable) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $this->payments->save($payment);

            return new JsonResponse(['error' => 'Payment gateway unavailable'], 502);
        }

        if (null === $session['url']) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $this->payments->save($payment);

            return new JsonResponse(['error' => 'Could not initiate payment session'], 502);
        }

        $payment->setStripeSessionId($session['id']);
        $this->payments->save($payment);

        return new JsonResponse([
            'paymentId' => (string) $payment->getId(),
            'orderId' => $orderId,
            'sessionId' => $session['id'],
            'url' => $session['url'],
        ], 201);
    }
}
