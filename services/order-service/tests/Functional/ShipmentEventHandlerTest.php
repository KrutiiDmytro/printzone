<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Messaging\Domain\IntegrationEvent;
use App\MessageHandler\ShipmentEventHandler;
use Symfony\Component\Uid\Uuid;

class ShipmentEventHandlerTest extends ApiTestCase
{
    private function handle(string $eventName, string $orderId): void
    {
        $handler = static::getContainer()->get(ShipmentEventHandler::class);
        $handler(new IntegrationEvent('shipment', $eventName, ['orderId' => $orderId]));
    }

    private function orderStatus(Order $order): string
    {
        $this->em->clear();

        return $this->em->getRepository(Order::class)->find($order->getId())->getStatus();
    }

    public function testShipmentCreatedMovesPaidToShipped(): void
    {
        $order = $this->seedOrder('PAID');

        $this->handle('ShipmentCreated', (string) $order->getId());

        self::assertSame('SHIPPED', $this->orderStatus($order));
    }

    public function testShipmentDeliveredMovesShippedToDelivered(): void
    {
        $order = $this->seedOrder('SHIPPED');

        $this->handle('ShipmentDelivered', (string) $order->getId());

        self::assertSame('DELIVERED', $this->orderStatus($order));
    }

    public function testTransitionsAreForwardOnly(): void
    {
        // A pending order is not advanced by ShipmentCreated (expects PAID).
        $order = $this->seedOrder('PENDING');

        $this->handle('ShipmentCreated', (string) $order->getId());

        self::assertSame('PENDING', $this->orderStatus($order));
    }

    public function testReplayIsIdempotent(): void
    {
        $order = $this->seedOrder('DELIVERED');

        // Already delivered → ShipmentDelivered is a no-op, never moves backwards.
        $this->handle('ShipmentDelivered', (string) $order->getId());

        self::assertSame('DELIVERED', $this->orderStatus($order));
    }

    public function testUnknownOrderIsNoop(): void
    {
        $this->handle('ShipmentCreated', (string) Uuid::v4());

        // No exception; nothing to assert beyond a clean run.
        self::assertTrue(true);
    }
}
