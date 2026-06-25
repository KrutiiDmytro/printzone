<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\Messaging\Domain\IntegrationEvent;
use App\Repository\OutboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reads unpublished outbox rows and publishes them to RabbitMQ, then marks them
 * published. Publish-then-mark gives at-least-once delivery (consumers must be
 * idempotent); a crash mid-batch simply re-publishes on the next run.
 */
final class OutboxRelay
{
    public function __construct(
        private readonly OutboxMessageRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function relayBatch(int $limit = 100): int
    {
        $messages = $this->repository->findUnpublished($limit);

        foreach ($messages as $message) {
            $routingKey = $message->getAggregate().'.'.$message->getEventName();

            $this->bus->dispatch(
                new IntegrationEvent(
                    $message->getAggregate(),
                    $message->getEventName(),
                    $message->getPayload(),
                    $message->getTraceId(),
                ),
                [new AmqpStamp($routingKey)],
            );

            $message->markPublished();
        }

        $this->em->flush();

        return \count($messages);
    }
}
