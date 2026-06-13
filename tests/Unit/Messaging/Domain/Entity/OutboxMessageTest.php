<?php

namespace App\Tests\Unit\Messaging\Domain\Entity;

use App\Messaging\Domain\Entity\OutboxMessage;
use PHPUnit\Framework\TestCase;

class OutboxMessageTest extends TestCase
{
    public function testConstructInitializesFields(): void
    {
        $msg = new OutboxMessage('order', 'OrderPaid', ['orderId' => 5], 'trace-1');

        $this->assertSame('order', $msg->getAggregate());
        $this->assertSame('OrderPaid', $msg->getEventName());
        $this->assertSame(['orderId' => 5], $msg->getPayload());
        $this->assertSame('trace-1', $msg->getTraceId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $msg->getCreatedAt());
        $this->assertNull($msg->getPublishedAt());
    }

    public function testMarkPublishedSetsTimestamp(): void
    {
        $msg = new OutboxMessage('order', 'OrderPaid', []);

        $this->assertNull($msg->getPublishedAt());

        $msg->markPublished();

        $this->assertInstanceOf(\DateTimeImmutable::class, $msg->getPublishedAt());
    }
}
