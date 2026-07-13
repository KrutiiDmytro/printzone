<?php

namespace App\Tests\Unit\MessageHandler;

use App\Client\StorageClient;
use App\Entity\ExportJob;
use App\Enum\ExportFormat;
use App\Enum\ExportStatus;
use App\Enum\ExportType;
use App\Extractor\ExportExtractorInterface;
use App\Message\ProcessExportMessage;
use App\MessageHandler\ProcessExportHandler;
use App\Repository\ExportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class ProcessExportHandlerTest extends TestCase
{
    public function testHappyPathWritesFileMarksCompletedAndEmails(): void
    {
        $job = new ExportJob(ExportType::Products, ExportFormat::Csv, 'admin@example.com');

        $product = $this->createMock(ExportExtractorInterface::class);
        $product->method('extract')->willReturn([['id' => '1', 'name' => 'Mug']]);

        $storage = $this->createMock(StorageClient::class);
        $storage->expects(self::once())->method('write')
            ->with(self::stringContains('exports/products/csv/'), self::stringContains('id,name'), 'text/csv');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $this->handler($job, $product, $storage, $mailer)(new ProcessExportMessage((string) $job->getId()));

        self::assertSame(ExportStatus::Completed, $job->getStatus());
        self::assertStringStartsWith('exports/products/csv/', (string) $job->getFilePath());
    }

    public function testExtractorFailureMarksFailedAndEmails(): void
    {
        $job = new ExportJob(ExportType::Products, ExportFormat::Json, 'admin@example.com');

        $product = $this->createMock(ExportExtractorInterface::class);
        $product->method('extract')->willThrowException(new \RuntimeException('catalog down'));

        $storage = $this->createMock(StorageClient::class);
        $storage->expects(self::never())->method('write');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $this->handler($job, $product, $storage, $mailer)(new ProcessExportMessage((string) $job->getId()));

        self::assertSame(ExportStatus::Failed, $job->getStatus());
        self::assertStringContainsString('catalog down', (string) $job->getErrorMessage());
    }

    public function testAlreadyCompletedJobIsSkipped(): void
    {
        $job = new ExportJob(ExportType::Products, ExportFormat::Csv, 'admin@example.com');
        $job->markCompleted('exports/products/csv/old.csv');

        $product = $this->createMock(ExportExtractorInterface::class);
        $product->expects(self::never())->method('extract');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($job, $product, $this->createMock(StorageClient::class), $mailer)(
            new ProcessExportMessage((string) $job->getId())
        );

        self::assertSame(ExportStatus::Completed, $job->getStatus());
    }

    private function handler(
        ExportJob $job,
        ExportExtractorInterface $product,
        StorageClient $storage,
        MailerInterface $mailer,
    ): ProcessExportHandler {
        $repository = $this->createMock(ExportJobRepository::class);
        $repository->method('find')->willReturn($job);

        $noopExtractor = $this->createMock(ExportExtractorInterface::class);

        return new ProcessExportHandler(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $product,
            $noopExtractor,
            $noopExtractor,
            $storage,
            $mailer,
            'admin@example.com',
        );
    }
}
