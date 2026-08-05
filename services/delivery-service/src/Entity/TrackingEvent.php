<?php

namespace App\Entity;

use App\Repository\TrackingEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Event-sourced tracking record: an append-only log of a shipment's status
 * history. Rows are never updated or deleted; Shipment.status is just the latest
 * projection of this log.
 */
#[ORM\Entity(repositoryClass: TrackingEventRepository::class)]
#[ORM\Table(name: 'tracking_events')]
#[ORM\Index(columns: ['shipment_id', 'occurred_at'], name: 'idx_tracking_shipment')]
class TrackingEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['tracking:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(name: 'shipment_id', referencedColumnName: 'id', nullable: false)]
    private Shipment $shipment;

    #[ORM\Column(length: 100)]
    #[Groups(['tracking:read'])]
    private string $status;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['tracking:read'])]
    private ?string $location;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tracking:read'])]
    private ?string $description;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tracking:read'])]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $recordedAt;

    public function __construct(
        Shipment $shipment,
        string $status,
        ?string $location = null,
        ?string $description = null,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->id = Uuid::v4();
        $this->shipment = $shipment;
        $this->status = $status;
        $this->location = $location;
        $this->description = $description;
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getShipment(): Shipment
    {
        return $this->shipment;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
