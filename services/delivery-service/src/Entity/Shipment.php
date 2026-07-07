<?php

namespace App\Entity;

use App\Repository\ShipmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * A shipment for a paid order. `status` is a projection of the append-only
 * tracking_events log (never the source of truth). `orderId` is unique so
 * consuming OrderPaid is idempotent — one shipment per order.
 */
#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
#[ORM\Table(name: 'shipments')]
#[ORM\UniqueConstraint(name: 'uniq_shipments_order_id', columns: ['order_id'])]
#[ORM\Index(columns: ['tracking_number'], name: 'idx_shipments_tracking')]
#[ORM\Index(columns: ['status'], name: 'idx_shipments_status')]
class Shipment
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PICKED_UP = 'PICKED_UP';
    public const STATUS_IN_TRANSIT = 'IN_TRANSIT';
    public const STATUS_OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['shipment:read'])]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    #[Groups(['shipment:read'])]
    private Uuid $orderId;

    #[ORM\Column(length: 50)]
    #[Groups(['shipment:read'])]
    private string $provider;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['shipment:read'])]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 32)]
    #[Groups(['shipment:read'])]
    private string $status = self::STATUS_PENDING;

    /**
     * Delivery address snapshot carried from OrderPaid.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['shipment:read'])]
    private array $address;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['shipment:read'])]
    private ?\DateTimeImmutable $estimatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['shipment:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $address
     */
    public function __construct(Uuid $orderId, string $provider, array $address)
    {
        $this->id = Uuid::v4();
        $this->orderId = $orderId;
        $this->provider = $provider;
        $this->address = $address;
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;

        return $this;
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

    /**
     * @return array<string, string>
     */
    public function getAddress(): array
    {
        return $this->address;
    }

    public function getEstimatedAt(): ?\DateTimeImmutable
    {
        return $this->estimatedAt;
    }

    public function setEstimatedAt(?\DateTimeImmutable $estimatedAt): static
    {
        $this->estimatedAt = $estimatedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
