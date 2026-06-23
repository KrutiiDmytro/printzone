<?php

namespace App\Repository;

use App\Catalog\Domain\Entity\PrinterModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrinterModel>
 */
class PrinterModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrinterModel::class);
    }

    /** @return PrinterModel[] */
    public function searchByName(string $q, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->where('LOWER(m.name) LIKE LOWER(:q) OR LOWER(m.brandName) LIKE LOWER(:q)')
            ->setParameter('q', '%'.$q.'%')
            ->orderBy('m.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return PrinterModel[] */
    public function findByBrandSlug(string $brandSlug): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.brandSlug = :slug')
            ->setParameter('slug', $brandSlug)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
