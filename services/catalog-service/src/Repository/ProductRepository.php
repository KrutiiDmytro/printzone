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
        $qb = $this->filtered($filters)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        match ($filters['sort'] ?? '') {
            'price_asc' => $qb->orderBy('p.price', 'ASC'),
            'price_desc' => $qb->orderBy('p.price', 'DESC'),
            default => $qb->orderBy('p.id', 'ASC'),
        };

        return $qb->getQuery()->getResult();
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
     * Min/max price (cents) across available products. Honours the same filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{min: int, max: int}
     */
    public function priceRange(array $filters): array
    {
        $row = $this->filtered($filters)
            ->select('MIN(p.price) AS minp, MAX(p.price) AS maxp')
            ->getQuery()
            ->getSingleResult();

        return ['min' => (int) ($row['minp'] ?? 0), 'max' => (int) ($row['maxp'] ?? 0)];
    }

    /**
     * @param string[] $ids
     *
     * @return Product[]
     */
    public function findByIds(array $ids): array
    {
        $valid = array_values(array_filter($ids, static fn (string $id): bool => Uuid::isValid($id)));
        if ([] === $valid) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $valid)
            ->getQuery()
            ->getResult();
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
        if (!empty($filters['categorySlug'])) {
            $qb->join('p.category', 'c')->andWhere('c.slug = :categorySlug')
                ->setParameter('categorySlug', (string) $filters['categorySlug']);
        }
        if (!empty($filters['brandSlug'])) {
            $qb->join('p.brand', 'b')->andWhere('b.slug = :brandSlug')
                ->setParameter('brandSlug', (string) $filters['brandSlug']);
        }
        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(p.name) LIKE :q')
                ->setParameter('q', '%'.strtolower((string) $filters['q']).'%');
        }
        if (isset($filters['isFeatured']) && '' !== $filters['isFeatured']) {
            $qb->andWhere('p.isFeatured = :featured')->setParameter('featured', (bool) $filters['isFeatured']);
        }
        if (!empty($filters['availableOnly'])) {
            $qb->andWhere('p.stock > 0');
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
