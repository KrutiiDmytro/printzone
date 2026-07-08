<?php

declare(strict_types=1);

namespace App\Delivery;

/**
 * Deterministic in-process provider (default). Derives the tracking number from
 * the order id so it is stable across retries and predictable in tests — no
 * network, no external account required.
 */
final class FakeProvider implements DeliveryProviderInterface
{
    public const NAME = 'fake';

    public function createShipment(string $orderId, array $address): ShipmentDraft
    {
        $trackingNumber = 'FAKE'.strtoupper(substr(hash('sha256', $orderId), 0, 14));

        return new ShipmentDraft(
            $trackingNumber,
            self::NAME,
            (new \DateTimeImmutable())->modify('+3 days'),
        );
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
