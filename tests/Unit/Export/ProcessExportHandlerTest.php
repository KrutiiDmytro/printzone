<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Entity\ExportJob;
use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportStatus;
use App\Export\Enum\ExportType;
use App\Export\Extractor\ExportExtractorInterface;
use App\Export\Message\ProcessExportMessage;
use App\Export\MessageHandler\ProcessExportHandler;
use App\Repository\ExportJobRepository;
use App\Storage\FileStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class ProcessExportHandlerTest extends TestCase
{
    private function makeHandler(
        ExportJobRepository $repo,
        EntityManagerInterface $em,
        FileStorageInterface $storage,
        MailerInterface $mailer,
        ExportExtractorInterface $productExtractor = null,
        ExportExtractorInterface $orderExtractor = null,
        ExportExtractorInterface $userExtractor = null,
    ): ProcessExportHandler {
        return new ProcessExportHandler(
            $repo,
            $em,
            $productExtractor ?? $this->createMock(ExportExtractorInterface::class),
            $orderExtractor   ?? $this->createMock(ExportExtractorInterface::class),
            $userExtractor    ?? $this->createMock(ExportExtractorInterface::class),
            $storage,
            $mailer,
            'admin@example.com',
        );
    }

    public function testReturnsEarlyWhenJobNotFound(): void
    {
        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $storage = $this->createMock(FileStorageInterface::class);
        $mailer  = $this->createMock(MailerInterface::class);

        $handler = $this->makeHandler($repo, $em, $storage, $mailer);
        $handler(new ProcessExportMessage(999));
    }

    public function testSuccessfulExportCompletesJob(): void
    {
        $job = new ExportJob(ExportType::Products, ExportFormat::Json, 'admin@example.com');

        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('find')->willReturn($job);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('flush');

        $productExtractor = $this->createMock(ExportExtractorInterface::class);
        $productExtractor->method('extract')->willReturn([
            ['id' => 1, 'name' => 'Test', 'price' => '10.00'],
        ]);

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects($this->once())->method('write');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $handler = $this->makeHandler($repo, $em, $storage, $mailer, $productExtractor);
        $handler(new ProcessExportMessage(1));

        $this->assertSame(ExportStatus::Completed, $job->getStatus());
        $this->assertNotNull($job->getFilePath());
        $this->assertNull($job->getErrorMessage());
    }

    public function testFailedExportMarksJobFailed(): void
    {
        $job = new ExportJob(ExportType::Orders, ExportFormat::Csv, 'admin@example.com');

        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('find')->willReturn($job);

        $em = $this->createMock(EntityManagerInterface::class);

        $orderExtractor = $this->createMock(ExportExtractorInterface::class);
        $orderExtractor->method('extract')->willThrowException(new \RuntimeException('DB error'));

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects($this->never())->method('write');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $handler = $this->makeHandler($repo, $em, $storage, $mailer, null, $orderExtractor);
        $handler(new ProcessExportMessage(2));

        $this->assertSame(ExportStatus::Failed, $job->getStatus());
        $this->assertStringContainsString('DB error', (string) $job->getErrorMessage());
        $this->assertNull($job->getFilePath());
    }

    public function testUserExtractorIsUsedForUsersType(): void
    {
        $job = new ExportJob(ExportType::Users, ExportFormat::Csv, 'admin@example.com');

        $repo = $this->createMock(ExportJobRepository::class);
        $repo->method('find')->willReturn($job);

        $em      = $this->createMock(EntityManagerInterface::class);
        $storage = $this->createMock(FileStorageInterface::class);
        $mailer  = $this->createMock(MailerInterface::class);
        $mailer->method('send');

        $userExtractor = $this->createMock(ExportExtractorInterface::class);
        $userExtractor->expects($this->once())->method('extract')->willReturn([
            ['id' => 1, 'email' => 'u@example.com', 'full_name' => 'User', 'roles' => 'ROLE_USER'],
        ]);

        $handler = $this->makeHandler($repo, $em, $storage, $mailer, null, null, $userExtractor);
        $handler(new ProcessExportMessage(3));

        $this->assertSame(ExportStatus::Completed, $job->getStatus());
    }
}
