<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use App\Messaging\Application\OutboxRecorder;
use App\Repository\TrackingEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Appends a status to a shipment's event-sourced tracking log and refreshes the
 * Shipment.status projection, then records the outbound event. Shared by the
 * Nova Poshta webhook and the simulate-tracking command so both take the exact
 * same append-only path.
 */
final class TrackingRecorder
{
    public function __construct(
        private readonly TrackingEventRepository $trackingEvents,
        private readonly EntityManagerInterface $em,
        private readonly OutboxRecorder $outboxRecorder,
    ) {
    }

    /**
     * @return bool true when a new record was appended, false when the identical
     *              (status, occurredAt) already existed (idempotent replay)
     */
    public function record(
        Shipment $shipment,
        string $status,
        ?string $location = null,
        ?string $description = null,
        ?\DateTimeImmutable $occurredAt = null,
    ): bool {
        $occurredAt ??= new \DateTimeImmutable();

        if ($this->trackingEvents->existsFor($shipment, $status, $occurredAt)) {
            return false;
        }

        // Append-only: never update or delete existing tracking rows.
        $this->em->persist(new TrackingEvent($shipment, $status, $location, $description, $occurredAt));

        // Shipment.status is a projection of the latest tracking record.
        $shipment->setStatus($status);

        if (Shipment::STATUS_DELIVERED === $status) {
            $this->outboxRecorder->record('shipment', 'ShipmentDelivered', [
                'shipmentId' => (string) $shipment->getId(),
                'orderId' => (string) $shipment->getOrderId(),
                'deliveredAt' => $occurredAt->format(\DateTimeInterface::ATOM),
            ]);
        } else {
            $this->outboxRecorder->record('shipment', 'TrackingUpdated', [
                'shipmentId' => (string) $shipment->getId(),
                'orderId' => (string) $shipment->getOrderId(),
                'status' => $status,
                'location' => $location,
            ]);
        }

        $this->em->flush();

        return true;
    }
}
