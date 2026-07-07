<?php

declare(strict_types=1);

namespace App\Delivery;

/**
 * What a delivery provider returns when a shipment is registered with the
 * carrier: the tracking number to follow it, the provider name, and an optional
 * estimated delivery date.
 */
final class ShipmentDraft
{
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $provider,
        public readonly ?\DateTimeImmutable $estimatedAt = null,
    ) {
    }
}
