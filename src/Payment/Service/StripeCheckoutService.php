<?php

namespace App\Payment\Service;

use App\Order\Domain\Entity\Order;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeCheckoutService
{
    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private string $secretKey,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<int, array{product: \App\Catalog\View\ProductView, quantity: int, total: int}> $cartItems
     *
     * @return array{id: string, url: string}
     */
    public function createSession(Order $order, array $cartItems): array
    {
        $stripe = new StripeClient($this->secretKey);

        $lineItems = [];
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $item['product']->getPrice(),
                    'product_data' => [
                        'name' => $item['product']->getName(),
                    ],
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $successUrl = $this->urlGenerator->generate(
            'app_checkout_success',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        ).'?session_id={CHECKOUT_SESSION_ID}';

        $cancelUrl = $this->urlGenerator->generate(
            'app_checkout_cancel',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => $lineItems,
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
