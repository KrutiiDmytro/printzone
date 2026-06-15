<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RequeueExportJobsCommand;
use App\Export\Domain\Entity\ExportJob;
use App\Export\Enum\ExportStatus;
use App\Export\Message\ProcessExportMessage;
use App\Repository\ExportJobRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class RequeueExportJobsCommandTest extends TestCase
{
    public function testExecuteWithNoPendingJobsPrintsMessageAndSucceeds(): void
    {
        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('findBy')->with(['status' => ExportStatus::Pending])->willReturn([]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $tester = new CommandTester(new RequeueExportJobsCommand($repo, $bus));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No pending jobs found.', $tester->getDisplay());
    }

    public function testExecuteDispatchesMessageForEachPendingJob(): void
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();

        $job1 = $this->createMock(ExportJob::class);
        $job1->method('getId')->willReturn($id1);

        $job2 = $this->createMock(ExportJob::class);
        $job2->method('getId')->willReturn($id2);

        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('findBy')->willReturn([$job1, $job2]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(ProcessExportMessage::class))
            ->willReturn(new Envelope(new ProcessExportMessage('00000000-0000-0000-0000-000000000000')));

        $tester = new CommandTester(new RequeueExportJobsCommand($repo, $bus));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Requeued job #'.$id1, $output);
        $this->assertStringContainsString('Requeued job #'.$id2, $output);
    }
}
