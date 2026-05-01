<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Entity\ExportJob;
use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use App\Export\Message\ProcessExportMessage;
use App\Export\Service\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExportServiceTest extends TestCase
{
    public function testDispatchPersistsJobAndSendsMessage(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(ExportJob::class));
        $em->expects($this->once())->method('flush');

        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProcessExportMessage::class))
            ->willReturn(new Envelope(new ProcessExportMessage(0)));

        $service = new ExportService($em, $bus);
        $job = $service->dispatch(ExportType::Products, ExportFormat::Csv, 'admin@example.com');

        $this->assertInstanceOf(ExportJob::class, $job);
        $this->assertSame(ExportType::Products, $job->getType());
        $this->assertSame(ExportFormat::Csv, $job->getFormat());
        $this->assertSame('admin@example.com', $job->getRequestedBy());
    }

    public function testDispatchWithFiltersPassesThemToJob(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new ProcessExportMessage(0)));

        $filters = ['status' => 'PENDING', 'dateFrom' => '2026-01-01'];
        $service = new ExportService($em, $bus);
        $job = $service->dispatch(ExportType::Orders, ExportFormat::Json, 'admin@example.com', $filters);

        $this->assertSame($filters, $job->getFilters());
    }

    public function testDispatchWithEmptyFiltersStoresNull(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new ProcessExportMessage(0)));

        $service = new ExportService($em, $bus);
        $job = $service->dispatch(ExportType::Users, ExportFormat::Xml, 'admin@example.com', []);

        // ExportService передає [] як filters, але Job зберігає null якщо масив порожній
        $this->assertNull($job->getFilters());
    }
}
