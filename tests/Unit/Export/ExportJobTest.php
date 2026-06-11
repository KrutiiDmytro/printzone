<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Entity\ExportJob;
use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportStatus;
use App\Export\Enum\ExportType;
use PHPUnit\Framework\TestCase;

final class ExportJobTest extends TestCase
{
    private function makeJob(?array $filters = null): ExportJob
    {
        return new ExportJob(ExportType::Products, ExportFormat::Csv, 'admin@example.com', $filters);
    }

    public function testInitialStatusIsPending(): void
    {
        $job = $this->makeJob();

        $this->assertSame(ExportStatus::Pending, $job->getStatus());
        $this->assertNull($job->getFilePath());
        $this->assertNull($job->getCompletedAt());
        $this->assertNull($job->getErrorMessage());
    }

    public function testConstructorSetsFields(): void
    {
        $filters = ['priceMin' => '100'];
        $job = new ExportJob(ExportType::Orders, ExportFormat::Json, 'user@example.com', $filters);

        $this->assertSame(ExportType::Orders, $job->getType());
        $this->assertSame(ExportFormat::Json, $job->getFormat());
        $this->assertSame('user@example.com', $job->getRequestedBy());
        $this->assertSame($filters, $job->getFilters());
        $this->assertInstanceOf(\DateTimeInterface::class, $job->getCreatedAt());
    }

    public function testMarkProcessing(): void
    {
        $job = $this->makeJob();
        $job->markProcessing();

        $this->assertSame(ExportStatus::Processing, $job->getStatus());
    }

    public function testMarkCompleted(): void
    {
        $job = $this->makeJob();
        $job->markProcessing();
        $job->markCompleted('exports/products/csv/1-20260430.csv');

        $this->assertSame(ExportStatus::Completed, $job->getStatus());
        $this->assertSame('exports/products/csv/1-20260430.csv', $job->getFilePath());
        $this->assertInstanceOf(\DateTimeInterface::class, $job->getCompletedAt());
        $this->assertNull($job->getErrorMessage());
    }

    public function testMarkFailed(): void
    {
        $job = $this->makeJob();
        $job->markProcessing();
        $job->markFailed('Connection timeout');

        $this->assertSame(ExportStatus::Failed, $job->getStatus());
        $this->assertSame('Connection timeout', $job->getErrorMessage());
        $this->assertInstanceOf(\DateTimeInterface::class, $job->getCompletedAt());
        $this->assertNull($job->getFilePath());
    }

    public function testNullFiltersStoredAsNull(): void
    {
        $job = $this->makeJob(null);

        $this->assertNull($job->getFilters());
    }

    public function testEmptyFiltersStoredAsGiven(): void
    {
        $job = $this->makeJob([]);

        $this->assertSame([], $job->getFilters());
    }
}
