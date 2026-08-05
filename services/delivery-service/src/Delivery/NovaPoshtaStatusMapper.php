<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Entity\Shipment;

/**
 * Maps Nova Poshta tracking StatusCodes to our canonical shipment statuses.
 * Illustrative subset of the real code list; unknown codes fall back to
 * IN_TRANSIT so the shipment keeps moving rather than stalling.
 */
final class NovaPoshtaStatusMapper
{
    private const MAP = [
        '1' => Shipment::STATUS_PENDING,
        '2' => Shipment::STATUS_PENDING,
        '3' => Shipment::STATUS_IN_TRANSIT,
        '4' => Shipment::STATUS_IN_TRANSIT,
        '5' => Shipment::STATUS_IN_TRANSIT,
        '6' => Shipment::STATUS_OUT_FOR_DELIVERY,
        '7' => Shipment::STATUS_OUT_FOR_DELIVERY,
        '8' => Shipment::STATUS_OUT_FOR_DELIVERY,
        '9' => Shipment::STATUS_DELIVERED,
        '10' => Shipment::STATUS_DELIVERED,
        '11' => Shipment::STATUS_DELIVERED,
        '102' => Shipment::STATUS_FAILED,
        '103' => Shipment::STATUS_FAILED,
        '108' => Shipment::STATUS_FAILED,
    ];

    public function map(string $statusCode): string
    {
        return self::MAP[$statusCode] ?? Shipment::STATUS_IN_TRANSIT;
    }
}
