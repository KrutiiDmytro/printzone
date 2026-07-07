<?php

namespace App\Tests\Functional;

use App\Entity\TrackingEvent;
use Symfony\Component\Uid\Uuid;

class ShipmentApiTest extends ApiTestCase
{
    public function testGetRequiresAuth(): void
    {
        $shipment = $this->seedShipment('FAKE_G');

        $this->send('GET', '/api/shipments/'.$shipment->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetById(): void
    {
        $shipment = $this->seedShipment('FAKE_H');

        $this->send('GET', '/api/shipments/'.$shipment->getId(), [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame('FAKE_H', $body['trackingNumber']);
        self::assertSame((string) $shipment->getOrderId(), $body['orderId']);
    }

    public function testGetByIdNotFound(): void
    {
        $this->send('GET', '/api/shipments/'.Uuid::v4(), [], $this->serviceToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetByOrder(): void
    {
        $orderId = Uuid::v4();
        $this->seedShipment('FAKE_I', $orderId);

        $this->send('GET', '/api/shipments/order/'.$orderId, [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        self::assertSame('FAKE_I', $this->json()['trackingNumber']);
    }

    public function testTrackingHistoryReturnsAppendedEvents(): void
    {
        $shipment = $this->seedShipment('FAKE_J');
        $this->em->persist(new TrackingEvent($shipment, 'IN_TRANSIT', 'Kyiv', 'moving'));
        $this->em->flush();

        $this->send('GET', '/api/shipments/'.$shipment->getId().'/tracking', [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->json()['events']);
        self::assertSame('IN_TRANSIT', $this->json()['events'][0]['status']);
    }
}
