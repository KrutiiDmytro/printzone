<?php

namespace App\Tests\Unit\Messaging\Application;

use App\Messaging\Application\OutboxRecorder;
use App\Messaging\Domain\Entity\OutboxMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OutboxRecorderTest extends TestCase
{
    public function testRecordPersistsOutboxMessageWithoutFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(OutboxMessage::class));
        // Atomic with the caller's transaction: the recorder must not flush itself.
        $em->expects($this->never())->method('flush');

        (new OutboxRecorder($em))->record('order', 'OrderPaid', ['orderId' => 1], 'trace-1');
    }
}
