<?php

declare(strict_types=1);

namespace App\Export\Extractor;

use Doctrine\ORM\EntityManagerInterface;

final class OrderExtractor implements ExportExtractorInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function extract(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('o.id, o.status, o.totalAmount, o.createdAt, o.userEmail AS user_email')
            ->from('App\Order\Domain\Entity\Order', 'o')
            ->orderBy('o.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('o.createdAt >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($filters['dateFrom']));
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('o.createdAt <= :dateTo')
               ->setParameter('dateTo', new \DateTime($filters['dateTo'].' 23:59:59'));
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(function (array $row): array {
            return [
                'id' => (string) $row['id'],
                'user_email' => $row['user_email'] ?? '',
                'status' => $row['status'],
                'total_amount' => number_format($row['totalAmount'] / 100, 2),
                'created_at' => $row['createdAt'] instanceof \DateTimeInterface
                    ? $row['createdAt']->format('Y-m-d H:i:s')
                    : (string) $row['createdAt'],
            ];
        }, $rows);
    }
}
