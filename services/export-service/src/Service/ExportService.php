<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ExportJob;
use App\Enum\ExportFormat;
use App\Enum\ExportType;
use App\Message\ProcessExportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ExportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(ExportType $type, ExportFormat $format, string $requestedBy, array $filters = []): ExportJob
    {
        $job = new ExportJob($type, $format, $requestedBy, $filters ?: null);
        $this->em->persist($job);
        $this->em->flush();

        $this->bus->dispatch(new ProcessExportMessage((string) $job->getId()));

        return $job;
    }
}
