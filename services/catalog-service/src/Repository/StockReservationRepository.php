<?php

namespace App\Repository;

use App\Entity\StockReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<StockReservation>
 */
class StockReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockReservation::class);
    }

    public function findOneByOrderAndProduct(Uuid $orderId, Uuid $productId): ?StockReservation
    {
        return $this->findOneBy(['orderId' => $orderId, 'productId' => $productId]);
    }

    /**
     * @return StockReservation[]
     */
    public function findByOrder(Uuid $orderId): array
    {
        return $this->findBy(['orderId' => $orderId]);
    }
}
