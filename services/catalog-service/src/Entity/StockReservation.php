<?php

namespace App\Entity;

use App\Repository\StockReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One product line of an order's stock reservation, driven by the choreographed
 * checkout Saga. Lifecycle: HELD (OrderCreated) → COMMITTED (OrderPaid) or
 * RELEASED (OrderCancelled). The unique (order_id, product_id) pair makes the
 * at-least-once event delivery idempotent.
 */
#[ORM\Entity(repositoryClass: StockReservationRepository::class)]
#[ORM\Table(name: 'stock_reservations')]
#[ORM\UniqueConstraint(name: 'uniq_reservation_order_product', columns: ['order_id', 'product_id'])]
class StockReservation
{
    public const STATUS_HELD = 'HELD';
    public const STATUS_COMMITTED = 'COMMITTED';
    public const STATUS_RELEASED = 'RELEASED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $orderId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $productId;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $orderId, Uuid $productId, int $quantity, string $status = self::STATUS_HELD)
    {
        $this->id = Uuid::v4();
        $this->orderId = $orderId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrderId(): Uuid
    {
        return $this->orderId;
    }

    public function getProductId(): Uuid
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
