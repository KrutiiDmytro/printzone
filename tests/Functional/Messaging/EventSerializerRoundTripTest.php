<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messaging;

use App\Messaging\Domain\IntegrationEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Phase 5 step 1: the `events` transport must put JSON on the wire (not PHP-native)
 * so a non-PHP consumer (catalog-service) can read it, carrying the FQCN in the
 * `type` header so Messenger maps the message back on both sides.
 */
final class EventSerializerRoundTripTest extends KernelTestCase
{
    public function testIntegrationEventRoundTripsAsJson(): void
    {
        self::bootKernel();
        /** @var SerializerInterface $serializer */
        $serializer = self::getContainer()->get('messenger.transport.symfony_serializer');

        $event = new IntegrationEvent('order', 'OrderPaid', ['orderId' => 'abc-123', 'userEmail' => 'x@y.z', 'totalAmount' => 4242], 'trace-1');
        $encoded = $serializer->encode(new Envelope($event, [new AmqpStamp('order.OrderPaid')]));

        // Body must be JSON (cross-service readable), not PHP-serialized.
        self::assertJson($encoded['body']);
        $decodedJson = json_decode($encoded['body'], true);
        self::assertSame('OrderPaid', $decodedJson['eventName']);
        self::assertSame('abc-123', $decodedJson['payload']['orderId']);
        self::assertSame(IntegrationEvent::class, $encoded['headers']['type']);

        $decoded = $serializer->decode($encoded)->getMessage();
        self::assertInstanceOf(IntegrationEvent::class, $decoded);
        self::assertSame('order', $decoded->aggregate);
        self::assertSame('OrderPaid', $decoded->eventName);
        self::assertSame('abc-123', $decoded->payload['orderId']);
        self::assertSame('trace-1', $decoded->traceId);
    }
}
