<?php

declare(strict_types=1);

namespace App\Export\Extractor;

use Doctrine\ORM\EntityManagerInterface;

final class UserExtractor implements ExportExtractorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function extract(array $filters): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('u.id, u.email, u.fullName, u.roles')
            ->from('App\User\Domain\Entity\User', 'u')
            ->orderBy('u.id', 'ASC');

        if (!empty($filters['email'])) {
            $qb->andWhere('u.email LIKE :email')
               ->setParameter('email', '%' . $filters['email'] . '%');
        }
        if (!empty($filters['role'])) {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%' . $filters['role'] . '%');
        }

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(function (array $row): array {
            return [
                'id' => $row['id'],
                'email' => $row['email'],
                'full_name' => $row['fullName'] ?? '',
                'roles' => implode(', ', $row['roles']),
            ];
        }, $rows);
    }
}
