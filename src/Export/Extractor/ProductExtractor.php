<?php

declare(strict_types=1);

namespace App\Export\Extractor;

use Doctrine\ORM\EntityManagerInterface;

final class ProductExtractor implements ExportExtractorInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function extract(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p.id, p.name, p.description, p.price, p.stock, p.isFeatured, p.image, c.name AS category')
            ->from('App\Catalog\Domain\Entity\Product', 'p')
            ->leftJoin('p.category', 'c')
            ->orderBy('p.id', 'ASC');

        if (!empty($filters['category'])) {
            $qb->andWhere('c.id = :category')->setParameter('category', (int) $filters['category']);
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

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(function (array $row): array {
            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'] ?? '',
                'price' => number_format($row['price'] / 100, 2),
                'stock' => $row['stock'],
                'is_featured' => $row['isFeatured'] ? 'yes' : 'no',
                'category' => $row['category'] ?? '',
                'image' => $row['image'] ?? '',
            ];
        }, $rows);
    }
}
