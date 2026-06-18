<?php

namespace App\Controller;

use App\Messaging\Application\OutboxRecorder;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private string $webhookSecret,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private OutboxRecorder $outboxRecorder,
    ) {
    }

    #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'sig_header' => $sigHeader ? substr($sigHeader, 0, 30).'...' : 'missing',
            ]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleSessionCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            default => null,
        };

        return new Response('', Response::HTTP_OK);
    }

    private function handleSessionCompleted(object $session): void
    {
        $orderId = (string) ($session->metadata->order_id ?? '');
        if (!Uuid::isValid($orderId)) {
            $this->logger->warning('Stripe webhook: invalid order id', ['order_id' => $orderId]);

            return;
        }

        $order = $this->orderRepository->find(Uuid::fromString($orderId));

        if (null === $order) {
            $this->logger->warning('Stripe webhook: order not found', ['order_id' => $orderId]);

            return;
        }

        if ('PENDING' !== $order->getStatus()) {
            $this->logger->info('Stripe webhook: order already processed', [
                'order_id' => $orderId,
                'status' => $order->getStatus(),
            ]);

            return;
        }

        $order->setStatus('PAID');

        // Transactional outbox: the OrderPaid event commits atomically with the
        // status change in the single flush below, then the relay ships it to RabbitMQ.
        $this->outboxRecorder->record('order', 'OrderPaid', [
            'orderId' => (string) $order->getId(),
            'userId' => (string) $order->getUserId(),
            'userEmail' => $order->getUserEmail(),
            'totalAmount' => $order->getTotalAmount(),
            'paidAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $this->entityManager->flush();

        $this->logger->info('Stripe webhook: order marked as PAID', ['order_id' => $orderId]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $orderId = (string) ($paymentIntent->metadata->order_id ?? '');
        if (!Uuid::isValid($orderId)) {
            $this->logger->warning('Stripe webhook: invalid order id', ['order_id' => $orderId]);

            return;
        }

        $order = $this->orderRepository->find(Uuid::fromString($orderId));

        if (null === $order) {
            $this->logger->warning('Stripe webhook: order not found', ['order_id' => $orderId]);

            return;
        }

        if ('PENDING' !== $order->getStatus()) {
            return;
        }

        $order->setStatus('FAILED');

        // Release the HELD stock reservation in the Catalog Saga, atomically
        // with the status change (transactional outbox → relay → RabbitMQ).
        $this->outboxRecorder->record('order', 'OrderCancelled', [
            'orderId' => (string) $order->getId(),
            'items' => $order->toEventItems(),
        ]);

        $this->entityManager->flush();

        $this->logger->info('Stripe webhook: order marked as FAILED', ['order_id' => $orderId]);
    }
}
