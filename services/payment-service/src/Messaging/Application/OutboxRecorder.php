<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records a domain event into the transactional outbox. Persists only — the row
 * is flushed (committed) by the caller, in the same transaction as the state
 * change, so the event is never lost and never published without the change.
 */
final class OutboxRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $aggregate, string $eventName, array $payload, ?string $traceId = null): void
    {
        $this->em->persist(new OutboxMessage($aggregate, $eventName, $payload, $traceId));
    }
}
