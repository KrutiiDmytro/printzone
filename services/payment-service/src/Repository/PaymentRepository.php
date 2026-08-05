<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByOrderId(Uuid $orderId): ?Payment
    {
        return $this->findOneBy(['orderId' => $orderId]);
    }

    public function findByStripeSessionId(string $sessionId): ?Payment
    {
        return $this->findOneBy(['stripeSessionId' => $sessionId]);
    }

    public function save(Payment $payment, bool $flush = true): void
    {
        $this->getEntityManager()->persist($payment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
