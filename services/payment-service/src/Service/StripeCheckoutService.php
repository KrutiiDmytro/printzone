<?php

namespace App\Service;

use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeCheckoutService
{
    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private string $secretKey,
    ) {
    }

    /**
     * @param list<array{name: string, price: int, quantity: int}> $lineItems
     *
     * Keyed by the Order id (a string) — this service does not own the Order
     * aggregate, it only needs the id to stamp into Stripe metadata so the
     * webhook can map the event back to an order. Success/cancel URLs belong to
     * the monolith (it renders those pages), so the caller supplies them;
     * `successUrl` must carry Stripe's literal {CHECKOUT_SESSION_ID} placeholder.
     *
     * @return array{id: string, url: string|null}
     */
    public function createSession(string $orderId, array $lineItems, string $successUrl, string $cancelUrl): array
    {
        $stripe = new StripeClient($this->secretKey);

        $items = [];
        foreach ($lineItems as $item) {
            $items[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $item['price'],
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $items,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_id' => $orderId,
            ],
            'payment_intent_data' => [
                'metadata' => ['order_id' => $orderId],
            ],
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }
}
