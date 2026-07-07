<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Delivery\DeliveryProviderInterface;
use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use App\Messaging\Application\OutboxRecorder;
use App\Messaging\Domain\IntegrationEvent;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Delivery side of the checkout Saga. Consumes order-service's OrderPaid and
 * registers a shipment with the carrier, seeding the append-only tracking log
 * and emitting ShipmentCreated.
 *
 * Delivery is at-least-once, so this is idempotent: one shipment per order
 * (unique order_id) — a replayed OrderPaid finds the existing shipment and is a
 * no-op. The shipment insert, first tracking event and outbox row all commit in
 * one flush.
 */
#[AsMessageHandler]
final class OrderPaidHandler
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly EntityManagerInterface $em,
        private readonly DeliveryProviderInterface $provider,
        private readonly OutboxRecorder $outboxRecorder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        if ('order' !== $event->aggregate || 'OrderPaid' !== $event->eventName) {
            return;
        }

        $orderId = $event->payload['orderId'] ?? null;
        if (!\is_string($orderId) || !Uuid::isValid($orderId)) {
            $this->logger->warning('OrderPaid: invalid orderId', ['orderId' => $orderId]);

            return;
        }

        $orderUuid = Uuid::fromString($orderId);
        if (null !== $this->shipments->findOneByOrderId($orderUuid)) {
            $this->logger->info('OrderPaid: shipment already exists', ['orderId' => $orderId]);

            return;
        }

        /** @var array<string, string> $address */
        $address = \is_array($event->payload['shippingAddress'] ?? null) ? $event->payload['shippingAddress'] : [];

        $draft = $this->provider->createShipment($orderId, $address);

        $shipment = new Shipment($orderUuid, $draft->provider, $address);
        $shipment->setTrackingNumber($draft->trackingNumber);
        $shipment->setEstimatedAt($draft->estimatedAt);
        $shipment->setStatus(Shipment::STATUS_PENDING);
        $this->em->persist($shipment);

        // Event-sourced tracking: creation is the first record in the log.
        $this->em->persist(new TrackingEvent(
            $shipment,
            Shipment::STATUS_PENDING,
            null,
            'Shipment registered with carrier',
        ));

        $this->outboxRecorder->record('shipment', 'ShipmentCreated', [
            'shipmentId' => (string) $shipment->getId(),
            'orderId' => $orderId,
            'trackingNumber' => $draft->trackingNumber,
            'provider' => $draft->provider,
        ]);

        $this->em->flush();

        $this->logger->info('Shipment created from OrderPaid', [
            'orderId' => $orderId,
            'shipmentId' => (string) $shipment->getId(),
            'trackingNumber' => $draft->trackingNumber,
        ]);
    }
}
