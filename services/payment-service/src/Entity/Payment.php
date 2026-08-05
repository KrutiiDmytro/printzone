<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\UniqueConstraint(name: 'uniq_payments_order_id', columns: ['order_id'])]
#[ORM\Index(columns: ['stripe_session_id'], name: 'idx_payments_session')]
#[ORM\Index(columns: ['status'], name: 'idx_payments_status')]
class Payment
{
    public const STATUS_INITIATED = 'INITIATED';
    public const STATUS_SUCCEEDED = 'SUCCEEDED';
    public const STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['payment:read'])]
    private Uuid $id;

    // Cross-service reference to the Order domain (no FK). Unique → one payment
    // row per order, which makes create-session idempotent on retries.
    #[ORM\Column(type: 'uuid')]
    #[Groups(['payment:read'])]
    private Uuid $orderId;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:read'])]
    private ?string $stripeSessionId = null;

    #[ORM\Column]
    #[Groups(['payment:read'])]
    private int $amount;

    #[ORM\Column(length: 3)]
    #[Groups(['payment:read'])]
    private string $currency;

    #[ORM\Column(length: 32)]
    #[Groups(['payment:read'])]
    private string $status = self::STATUS_INITIATED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['payment:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['payment:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Uuid $orderId, int $amount, string $currency = 'eur')
    {
        $this->id = Uuid::v4();
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrderId(): Uuid
    {
        return $this->orderId;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;
        $this->touch();

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        $this->touch();

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
