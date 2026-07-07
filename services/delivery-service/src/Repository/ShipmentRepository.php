<?php

namespace App\Repository;

use App\Entity\Shipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Shipment>
 */
class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function findOneByOrderId(Uuid $orderId): ?Shipment
    {
        return $this->findOneBy(['orderId' => $orderId]);
    }

    public function save(Shipment $shipment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($shipment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
