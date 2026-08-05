<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ShipmentTest extends TestCase
{
    private function address(): array
    {
        return ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'city' => 'Kyiv'];
    }

    public function testNewShipmentStartsPendingWithoutTracking(): void
    {
        $shipment = new Shipment(Uuid::v4(), 'fake', $this->address());

        self::assertSame(Shipment::STATUS_PENDING, $shipment->getStatus());
        self::assertNull($shipment->getTrackingNumber());
        self::assertSame('fake', $shipment->getProvider());
        self::assertSame('Kyiv', $shipment->getAddress()['city']);
    }

    public function testTrackingAndStatusAreMutable(): void
    {
        $shipment = new Shipment(Uuid::v4(), 'fake', $this->address());

        $shipment->setTrackingNumber('FAKE123')->setStatus(Shipment::STATUS_DELIVERED);

        self::assertSame('FAKE123', $shipment->getTrackingNumber());
        self::assertSame(Shipment::STATUS_DELIVERED, $shipment->getStatus());
    }

    public function testTrackingEventDefaultsOccurredAtToNow(): void
    {
        $shipment = new Shipment(Uuid::v4(), 'fake', $this->address());
        $event = new TrackingEvent($shipment, Shipment::STATUS_IN_TRANSIT, 'Kyiv', 'On the way');

        self::assertSame(Shipment::STATUS_IN_TRANSIT, $event->getStatus());
        self::assertSame('Kyiv', $event->getLocation());
        self::assertSame($shipment, $event->getShipment());
        self::assertNotNull($event->getOccurredAt());
    }
}
