<?php

declare(strict_types=1);

namespace App\Messaging\Domain\Entity;

use App\Repository\OutboxMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Transactional outbox row. Written in the same DB transaction as a domain state
 * change, then published to RabbitMQ by the outbox relay and marked as published.
 */
#[ORM\Entity(repositoryClass: OutboxMessageRepository::class)]
#[ORM\Table(name: 'outbox', schema: 'messaging')]
#[ORM\Index(columns: ['published_at', 'created_at'], name: 'idx_outbox_unpublished')]
class OutboxMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $aggregate;

    #[ORM\Column(length: 100)]
    private string $eventName;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $traceId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(string $aggregate, string $eventName, array $payload, ?string $traceId = null)
    {
        $this->id = Uuid::v4();
        $this->aggregate = $aggregate;
        $this->eventName = $eventName;
        $this->payload = $payload;
        $this->traceId = $traceId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAggregate(): string
    {
        return $this->aggregate;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function markPublished(): void
    {
        $this->publishedAt = new \DateTimeImmutable();
    }
}
