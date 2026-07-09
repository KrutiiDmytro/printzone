<?php

namespace App\Tests\Unit\Messaging\Application;

use App\Messaging\Application\IntegrationEventHandler;
use App\Messaging\Domain\IntegrationEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The OrderPaid receipt moved to notification-service; the monolith handler is now
 * a log-only observer that just drains the catch-all `events_all` queue.
 */
class IntegrationEventHandlerTest extends TestCase
{
    public function testObservesEventWithoutError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');

        $handler = new IntegrationEventHandler($logger);
        $handler(new IntegrationEvent('order', 'OrderPaid', [
            'orderId' => 'o-1',
            'userEmail' => 'buyer@example.com',
            'totalAmount' => 5000,
        ]));
    }

    public function testHandlesAnyEventTypeAsNoOp(): void
    {
        $handler = new IntegrationEventHandler($this->createMock(LoggerInterface::class));

        // No handler-specific behaviour, no exception, regardless of the event.
        $handler(new IntegrationEvent('payment', 'PaymentFailed', ['orderId' => 'o-2']));
        $handler(new IntegrationEvent('delivery', 'ShipmentDelivered', ['shipmentId' => 's-1']));

        $this->expectNotToPerformAssertions();
    }
}
