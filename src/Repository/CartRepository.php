<?php

namespace App\Repository;

use App\Cart\Domain\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findOneByUserId(int $userId): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->addSelect('i')
            ->leftJoin('c.items', 'i')
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.updatedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
