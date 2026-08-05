<?php

declare(strict_types=1);

namespace App\Messaging\Domain;

/**
 * A domain event leaving the application boundary towards the broker. Routed to
 * the AMQP `events` transport with routing key `{aggregate}.{eventName}`.
 *
 * The FQCN is intentionally identical to the monolith's / order-service's /
 * catalog-service's copy: it travels in the message `type` header so every
 * service deserializes the JSON back into its own matching class.
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
