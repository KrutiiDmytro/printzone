<?php

declare(strict_types=1);

namespace App\Messaging\Domain;

/**
 * Cross-service contract: this MUST keep the same FQCN as the monolith's
 * App\Messaging\Domain\IntegrationEvent so the Messenger `type` header maps the
 * JSON message back to this class on the consuming side. Catalog only consumes
 * (Order.* events) to drive the stock-reservation Saga.
 *
 * @see \App\MessageHandler\OrderEventHandler
 */
final class IntegrationEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $aggregate,
        public readonly string $eventName,
        public readonly array $payload,
        public readonly ?string $traceId = null,
    ) {
    }
}
