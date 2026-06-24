<?php

namespace App\Service;

use App\Entity\Order;
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
     * The success/cancel URLs point back to the monolith (it owns the cart and
     * the rendered pages), so the caller supplies them. `successUrl` must carry
     * Stripe's literal {CHECKOUT_SESSION_ID} placeholder if it needs the id back.
     *
     * @return array{id: string, url: string|null}
     */
    public function createSession(Order $order, array $lineItems, string $successUrl, string $cancelUrl): array
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
                'order_id' => (string) $order->getId(),
            ],
            'payment_intent_data' => [
                'metadata' => ['order_id' => (string) $order->getId()],
            ],
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }
}
