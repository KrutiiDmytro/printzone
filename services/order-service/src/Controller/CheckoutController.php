<?php

namespace App\Controller;

use App\Client\PaymentClient;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Messaging\Application\OutboxRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Checkout entrypoint for the monolith. The monolith reads the cart, then POSTs
 * the line items here; this service owns the Order aggregate and delegates the
 * Stripe Checkout session to payment-service (over HTTP). Success/cancel URLs are
 * the monolith's (it renders those pages and clears the cart), so they arrive in
 * the request body.
 */
#[Route('/api/checkout')]
class CheckoutController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentClient $paymentClient,
        private readonly OutboxRecorder $outboxRecorder,
    ) {
    }

    #[Route('', name: 'api_checkout', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $userId = (string) ($data['userId'] ?? '');
        $userEmail = (string) ($data['userEmail'] ?? '');
        $items = $data['items'] ?? [];
        $shippingAddress = $data['shippingAddress'] ?? [];
        $successUrl = (string) ($data['successUrl'] ?? '');
        $cancelUrl = (string) ($data['cancelUrl'] ?? '');

        if (!Uuid::isValid($userId) || '' === $userEmail || !is_array($items) || [] === $items) {
            return new JsonResponse(['error' => 'Missing or invalid fields: userId, userEmail, items'], 400);
        }
        if (!is_array($shippingAddress) || [] === $shippingAddress) {
            return new JsonResponse(['error' => 'Missing or invalid field: shippingAddress'], 400);
        }
        if ('' === $successUrl || '' === $cancelUrl) {
            return new JsonResponse(['error' => 'Missing successUrl/cancelUrl'], 400);
        }

        $order = new Order();
        $order->setUserId(Uuid::fromString($userId));
        $order->setUserEmail($userEmail);
        $order->setStatus('PENDING');
        $order->setShippingAddress(array_map(strval(...), $shippingAddress));

        $total = 0;
        $lineItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || !Uuid::isValid((string) ($item['productId'] ?? ''))) {
                return new JsonResponse(['error' => 'Invalid item: productId required'], 400);
            }
            $name = (string) ($item['name'] ?? '');
            $price = (int) ($item['price'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProductId(Uuid::fromString((string) $item['productId']));
            $orderItem->setProductName($name);
            $orderItem->setPrice($price);
            $orderItem->setQuantity($quantity);
            $order->getItems()->add($orderItem);

            $total += $price * $quantity;
            $lineItems[] = ['name' => $name, 'price' => $price, 'quantity' => $quantity];
        }
        $order->setTotalAmount($total);

        $this->entityManager->persist($order);

        // Transactional outbox: OrderCreated commits atomically with the order
        // insert, then the relay ships it to RabbitMQ where the Catalog Saga
        // reserves stock (HELD) for these items.
        $this->outboxRecorder->record('order', 'OrderCreated', [
            'orderId' => (string) $order->getId(),
            'userId' => $userId,
            'items' => $order->toEventItems(),
            'totalAmount' => $order->getTotalAmount(),
        ]);

        $this->entityManager->flush();

        try {
            $session = $this->paymentClient->createSession((string) $order->getId(), $lineItems, $successUrl, $cancelUrl);
        } catch (\Throwable $e) {
            $this->failOrder($order);

            return new JsonResponse(['error' => 'Payment service is unavailable'], 502);
        }

        $order->setStripeSessionId($session['sessionId']);
        $this->entityManager->flush();

        return new JsonResponse([
            'orderId' => (string) $order->getId(),
            'url' => $session['url'],
        ], 201);
    }

    /**
     * Marks the order FAILED and emits OrderCancelled so the Catalog Saga
     * releases the HELD reservation; both commit in one flush.
     */
    private function failOrder(Order $order): void
    {
        $order->setStatus('FAILED');
        $this->outboxRecorder->record('order', 'OrderCancelled', [
            'orderId' => (string) $order->getId(),
            'items' => $order->toEventItems(),
        ]);
        $this->entityManager->flush();
    }
}
