<?php

namespace App\Repository;

use App\Cart\Domain\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findOneByUserId(Uuid $userId): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->addSelect('i')
            ->leftJoin('c.items', 'i')
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId, 'uuid')
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
