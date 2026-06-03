<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private string $webhookSecret,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
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
        $orderId = (int) ($session->metadata->order_id ?? 0);
        $order = $this->orderRepository->find($orderId);

        if ($order === null) {
            $this->logger->warning('Stripe webhook: order not found', ['order_id' => $orderId]);

            return;
        }

        if ($order->getStatus() !== 'PENDING') {
            $this->logger->info('Stripe webhook: order already processed', [
                'order_id' => $orderId,
                'status' => $order->getStatus(),
            ]);

            return;
        }

        $order->setStatus('PAID');
        $this->entityManager->flush();

        $this->logger->info('Stripe webhook: order marked as PAID', ['order_id' => $orderId]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $orderId = (int) ($paymentIntent->metadata->order_id ?? 0);
        $order = $this->orderRepository->find($orderId);

        if ($order === null) {
            $this->logger->warning('Stripe webhook: order not found', ['order_id' => $orderId]);

            return;
        }

        if ($order->getStatus() !== 'PENDING') {
            return;
        }

        $order->setStatus('FAILED');
        $this->entityManager->flush();

        $this->logger->info('Stripe webhook: order marked as FAILED', ['order_id' => $orderId]);
    }
}
