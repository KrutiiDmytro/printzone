<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ExportJob;
use App\Enum\ExportFormat;
use App\Enum\ExportStatus;
use App\Enum\ExportType;
use PHPUnit\Framework\TestCase;

class ExportJobTest extends TestCase
{
    public function testStartsPending(): void
    {
        $job = new ExportJob(ExportType::Products, ExportFormat::Csv, 'a@b.c', ['x' => 1]);

        self::assertSame(ExportStatus::Pending, $job->getStatus());
        self::assertSame('a@b.c', $job->getRequestedBy());
        self::assertSame(['x' => 1], $job->getFilters());
        self::assertNull($job->getCompletedAt());
    }

    public function testCompleteSetsPathAndTimestamp(): void
    {
        $job = new ExportJob(ExportType::Orders, ExportFormat::Json, 'a@b.c');
        $job->markProcessing();
        $job->markCompleted('exports/orders/json/x.json');

        self::assertSame(ExportStatus::Completed, $job->getStatus());
        self::assertSame('exports/orders/json/x.json', $job->getFilePath());
        self::assertNotNull($job->getCompletedAt());
    }

    public function testFailSetsError(): void
    {
        $job = new ExportJob(ExportType::Users, ExportFormat::Xml, 'a@b.c');
        $job->markFailed('boom');

        self::assertSame(ExportStatus::Failed, $job->getStatus());
        self::assertSame('boom', $job->getErrorMessage());
    }
}
