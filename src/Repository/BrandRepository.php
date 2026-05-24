<?php

namespace App\Repository;

use App\Catalog\Domain\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    public function findBySlug(string $slug): ?Brand
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
