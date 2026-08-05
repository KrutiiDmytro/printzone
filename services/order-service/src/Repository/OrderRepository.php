<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @param array{status?: string, dateFrom?: string, dateTo?: string} $filters
     *
     * @return Order[]
     */
    public function findByFilters(array $filters = [], int $limit = 500): array
    {
        $qb = $this->createQueryBuilder('o')
            ->addSelect('i')
            ->leftJoin('o.items', 'i')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('o.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($filters['dateFrom']));
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('o.createdAt <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($filters['dateTo'].' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Order[]
     */
    public function findByUserId(Uuid $userId): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('i')
            ->leftJoin('o.items', 'i')
            ->where('o.userId = :userId')
            ->setParameter('userId', $userId, 'uuid')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
