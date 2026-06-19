<?php

namespace App\Repository;

use App\Entity\Brand;
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

    public function save(Brand $brand, bool $flush = false): void
    {
        $this->getEntityManager()->persist($brand);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Brand $brand, bool $flush = false): void
    {
        $this->getEntityManager()->remove($brand);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Brand
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return Brand[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}
