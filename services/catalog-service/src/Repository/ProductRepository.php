<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return Product[]
     */
    public function findByFilters(array $filters, int $page, int $limit): array
    {
        return $this->filtered($filters)
            ->orderBy('p.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countByFilters(array $filters): int
    {
        return (int) $this->filtered($filters)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function filtered(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($filters['category']) && Uuid::isValid((string) $filters['category'])) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', Uuid::fromString((string) $filters['category']), 'uuid');
        }
        if (isset($filters['isFeatured']) && '' !== $filters['isFeatured']) {
            $qb->andWhere('p.isFeatured = :featured')->setParameter('featured', (bool) $filters['isFeatured']);
        }
        if (!empty($filters['priceMin'])) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', (int) $filters['priceMin']);
        }
        if (!empty($filters['priceMax'])) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', (int) $filters['priceMax']);
        }
        if (!empty($filters['stockMin'])) {
            $qb->andWhere('p.stock >= :stockMin')->setParameter('stockMin', (int) $filters['stockMin']);
        }
        if (!empty($filters['stockMax'])) {
            $qb->andWhere('p.stock <= :stockMax')->setParameter('stockMax', (int) $filters['stockMax']);
        }

        return $qb;
    }
}
