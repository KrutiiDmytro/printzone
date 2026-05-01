<?php

declare(strict_types=1);

namespace App\Export\Service;

use App\Export\Domain\Entity\ExportJob;
use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use App\Export\Message\ProcessExportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function dispatch(ExportType $type, ExportFormat $format, string $requestedBy, array $filters = []): ExportJob
    {
        $job = new ExportJob($type, $format, $requestedBy, $filters ?: null);
        $this->em->persist($job);
        $this->em->flush();

        $this->bus->dispatch(new ProcessExportMessage((int) $job->getId()));

        return $job;
    }
}
