<?php

declare(strict_types=1);

namespace App\Delivery;

/**
 * A logistics provider (Nova Poshta, Ukrposhta, ...). Implementations register a
 * shipment with the carrier and return the tracking handle. Kept behind this
 * interface so the real integration and the deterministic test double are
 * interchangeable via the DELIVERY_PROVIDER env var.
 */
interface DeliveryProviderInterface
{
    /**
     * @param array<string, string> $address delivery snapshot from OrderPaid
     */
    public function createShipment(string $orderId, array $address): ShipmentDraft;

    /**
     * Whether the provider's upstream API is reachable (used by /health/ready).
     */
    public function isAvailable(): bool;
}
