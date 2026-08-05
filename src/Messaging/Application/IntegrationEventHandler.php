<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\Messaging\Domain\IntegrationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes integration events delivered from RabbitMQ.
 *
 * The customer receipt on OrderPaid has moved to the standalone notification-service
 * (Phase 7). This handler no longer sends anything — it stays as a log-only observer
 * whose sole remaining job is to drain the monolith's catch-all `events_all` queue
 * (bound to `#`) so it does not grow unbounded now that nothing else in the monolith
 * consumes integration events. Removing the monolith's event consumption entirely
 * (dropping the queue + the worker's `events` transport) is a separate teardown.
 */
#[AsMessageHandler]
final class IntegrationEventHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IntegrationEvent $event): void
    {
        $this->logger->debug('Integration event observed (no action in monolith)', [
            'event' => $event->eventName,
            'aggregate' => $event->aggregate,
            'traceId' => $event->traceId,
        ]);
    }
}
