<?php

namespace App\Tests\Functional;

use App\Entity\OutboxMessage;
use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use App\Messaging\Domain\IntegrationEvent;
use App\MessageHandler\OrderPaidHandler;
use Symfony\Component\Uid\Uuid;

class OrderPaidHandlerTest extends ApiTestCase
{
    private function handle(string $aggregate, string $eventName, array $payload): void
    {
        $handler = static::getContainer()->get(OrderPaidHandler::class);
        $handler(new IntegrationEvent($aggregate, $eventName, $payload));
    }

    private function orderPaidPayload(string $orderId): array
    {
        return [
            'orderId' => $orderId,
            'userEmail' => 'buyer@example.com',
            'totalAmount' => 1999,
            'shippingAddress' => [
                'firstName' => 'Ada', 'lastName' => 'Lovelace', 'address' => '1 Analytical St',
                'city' => 'Kyiv', 'country' => 'UA', 'postcode' => '01001', 'phone' => '+380001112233',
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function outboxEventNames(): array
    {
        $this->em->clear();

        return array_map(
            fn (OutboxMessage $m) => $m->getEventName(),
            $this->em->getRepository(OutboxMessage::class)->findAll()
        );
    }

    public function testOrderPaidCreatesShipmentAndEmitsShipmentCreated(): void
    {
        $orderId = (string) Uuid::v4();

        $this->handle('order', 'OrderPaid', $this->orderPaidPayload($orderId));

        $shipment = $this->em->getRepository(Shipment::class)->findOneBy(['orderId' => Uuid::fromString($orderId)]);
        self::assertNotNull($shipment);
        self::assertSame(Shipment::STATUS_PENDING, $shipment->getStatus());
        self::assertSame('fake', $shipment->getProvider());
        self::assertNotNull($shipment->getTrackingNumber());
        self::assertSame('Kyiv', $shipment->getAddress()['city']);

        // Event-sourced log seeded with the creation record.
        self::assertCount(1, $this->em->getRepository(TrackingEvent::class)->findAll());

        $created = $this->em->getRepository(OutboxMessage::class)->findOneBy(['eventName' => 'ShipmentCreated']);
        self::assertNotNull($created);
        self::assertSame($orderId, $created->getPayload()['orderId']);
        self::assertSame($shipment->getTrackingNumber(), $created->getPayload()['trackingNumber']);
    }

    public function testReplayIsIdempotent(): void
    {
        $orderId = (string) Uuid::v4();
        $payload = $this->orderPaidPayload($orderId);

        $this->handle('order', 'OrderPaid', $payload);
        $this->handle('order', 'OrderPaid', $payload);

        // Unique order_id → one shipment, one creation record, one event.
        self::assertCount(1, $this->em->getRepository(Shipment::class)->findAll());
        self::assertCount(1, $this->em->getRepository(TrackingEvent::class)->findAll());
        self::assertSame(['ShipmentCreated'], $this->outboxEventNames());
    }

    public function testOtherOrderEventsAreIgnored(): void
    {
        $this->handle('order', 'OrderCreated', $this->orderPaidPayload((string) Uuid::v4()));

        self::assertCount(0, $this->em->getRepository(Shipment::class)->findAll());
    }

    public function testInvalidOrderIdIsNoop(): void
    {
        $this->handle('order', 'OrderPaid', ['orderId' => 'not-a-uuid']);

        self::assertCount(0, $this->em->getRepository(Shipment::class)->findAll());
    }
}
