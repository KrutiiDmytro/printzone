<?php

namespace App\Tests\Unit\Messaging\Application;

use App\Messaging\Application\OutboxRelay;
use App\Messaging\Domain\Entity\OutboxMessage;
use App\Messaging\Domain\IntegrationEvent;
use App\Repository\OutboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OutboxRelayTest extends TestCase
{
    public function testRelayBatchPublishesUnpublishedAndMarksThem(): void
    {
        $m1 = new OutboxMessage('order', 'OrderPaid', ['orderId' => 1]);
        $m2 = new OutboxMessage('order', 'OrderPaid', ['orderId' => 2]);

        $repo = $this->createMock(OutboxMessageRepository::class);
        $repo->method('findUnpublished')->willReturn([$m1, $m2]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(IntegrationEvent::class))
            ->willReturnCallback(fn (object $message): Envelope => new Envelope($message));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $count = (new OutboxRelay($repo, $em, $bus))->relayBatch();

        $this->assertSame(2, $count);
        $this->assertNotNull($m1->getPublishedAt());
        $this->assertNotNull($m2->getPublishedAt());
    }

    public function testRelayBatchWithNoMessagesPublishesNothing(): void
    {
        $repo = $this->createMock(OutboxMessageRepository::class);
        $repo->method('findUnpublished')->willReturn([]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $count = (new OutboxRelay($repo, $em, $bus))->relayBatch();

        $this->assertSame(0, $count);
    }
}
