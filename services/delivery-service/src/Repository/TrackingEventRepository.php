<?php

namespace App\Repository;

use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackingEvent>
 */
class TrackingEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackingEvent::class);
    }

    /**
     * The full append-only history for a shipment, newest first.
     *
     * @return TrackingEvent[]
     */
    public function findByShipment(Shipment $shipment): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.shipment = :shipment')
            ->setParameter('shipment', $shipment->getId(), 'uuid')
            ->orderBy('t.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Idempotency guard for replayed webhooks/events: whether an identical
     * (status, occurredAt) record already exists for this shipment.
     */
    public function existsFor(Shipment $shipment, string $status, \DateTimeImmutable $occurredAt): bool
    {
        return null !== $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.shipment = :shipment')
            ->andWhere('t.status = :status')
            ->andWhere('t.occurredAt = :occurredAt')
            ->setParameter('shipment', $shipment->getId(), 'uuid')
            ->setParameter('status', $status)
            ->setParameter('occurredAt', $occurredAt, 'datetime_immutable')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
