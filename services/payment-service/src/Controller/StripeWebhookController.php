<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Messaging\Application\OutboxRecorder;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Stripe posts here directly (public, no JWT — authenticated by the signed
 * payload). Unlike the old order-service webhook, this does NOT touch the Order:
 * it resolves its own Payment row and emits PaymentSucceeded / PaymentFailed, and
 * order-service transitions the Order when it consumes that event (step 4).
 */
class StripeWebhookController
{
    public function __construct(
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private string $webhookSecret,
        private PaymentRepository $payments,
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
            'checkout.session.completed' => $this->resolve($event->data->object, Payment::STATUS_SUCCEEDED, 'PaymentSucceeded'),
            'payment_intent.payment_failed' => $this->resolve($event->data->object, Payment::STATUS_FAILED, 'PaymentFailed'),
            default => null,
        };

        return new Response('', Response::HTTP_OK);
    }

    private function resolve(object $object, string $newStatus, string $eventName): void
    {
        $orderId = (string) ($object->metadata->order_id ?? '');
        if (!Uuid::isValid($orderId)) {
            $this->logger->warning('Stripe webhook: invalid order id', ['order_id' => $orderId]);

            return;
        }

        $payment = $this->payments->findOneByOrderId(Uuid::fromString($orderId));
        if (null === $payment) {
            $this->logger->warning('Stripe webhook: payment not found', ['order_id' => $orderId]);

            return;
        }

        // Idempotent: only a payment still awaiting its outcome may transition.
        // A replayed webhook (Stripe retries) or an already-resolved payment is a
        // no-op, so no duplicate Payment* event is ever emitted.
        if (Payment::STATUS_INITIATED !== $payment->getStatus()) {
            $this->logger->info('Stripe webhook: payment already resolved', [
                'order_id' => $orderId,
                'status' => $payment->getStatus(),
            ]);

            return;
        }

        $payment->setStatus($newStatus);

        // Transactional outbox: the Payment* event commits atomically with the
        // status change in the single flush below; the relay then ships it to
        // RabbitMQ (routing key payment.*) where order-service transitions the Order.
        $this->outboxRecorder->record('payment', $eventName, [
            'orderId' => $orderId,
            'paymentId' => (string) $payment->getId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
        ]);

        $this->entityManager->flush();

        $this->logger->info('Stripe webhook: payment '.$newStatus, ['order_id' => $orderId]);
    }
}
