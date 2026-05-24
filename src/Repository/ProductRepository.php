<?php

namespace App\Repository;

use App\Catalog\Domain\Entity\Brand;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findFeatured(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.isFeatured = true')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllAvailable(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.stock > 0')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.category = :category')
            ->andWhere('p.stock > 0')
            ->setParameter('category', $category)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByBrand(Brand $brand): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.brand = :brand')
            ->andWhere('p.stock > 0')
            ->setParameter('brand', $brand)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByBrandAndCategory(Brand $brand, Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.brand = :brand')
            ->andWhere('p.category = :category')
            ->andWhere('p.stock > 0')
            ->setParameter('brand', $brand)
            ->setParameter('category', $category)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{category?: Category|null, brand?: Brand|null, minPrice?: int|null, maxPrice?: int|null, sort?: string} $filters
     */
    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->where('p.stock > 0');

        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')->setParameter('category', $filters['category']);
        }
        if (!empty($filters['brand'])) {
            $qb->andWhere('p.brand = :brand')->setParameter('brand', $filters['brand']);
        }
        if (isset($filters['minPrice'])) {
            $qb->andWhere('p.price >= :minPrice')->setParameter('minPrice', $filters['minPrice']);
        }
        if (isset($filters['maxPrice'])) {
            $qb->andWhere('p.price <= :maxPrice')->setParameter('maxPrice', $filters['maxPrice']);
        }

        match ($filters['sort'] ?? '') {
            'price_asc'  => $qb->orderBy('p.price', 'ASC'),
            'price_desc' => $qb->orderBy('p.price', 'DESC'),
            'newest'     => $qb->orderBy('p.id', 'DESC'),
            default      => $qb->orderBy('p.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /** Returns ['min' => int, 'max' => int] prices in cents across all available products. */
    public function getPriceRange(): array
    {
        $row = $this->createQueryBuilder('p')
            ->select('MIN(p.price) as minPrice, MAX(p.price) as maxPrice')
            ->where('p.stock > 0')
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => (int) ($row['minPrice'] ?? 0),
            'max' => (int) ($row['maxPrice'] ?? 0),
        ];
    }
}
