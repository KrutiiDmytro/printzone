<?php

declare(strict_types=1);

namespace App\Command;

use App\Export\Enum\ExportStatus;
use App\Export\Message\ProcessExportMessage;
use App\Repository\ExportJobRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:export:requeue-pending', description: 'Requeue export jobs stuck in pending status')]
final class RequeueExportJobsCommand extends Command
{
    public function __construct(
        private readonly ExportJobRepository $repository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobs = $this->repository->findBy(['status' => ExportStatus::Pending]);

        if (empty($jobs)) {
            $output->writeln('No pending jobs found.');

            return Command::SUCCESS;
        }

        foreach ($jobs as $job) {
            $this->bus->dispatch(new ProcessExportMessage((int) $job->getId()));
            $output->writeln('Requeued job #'.$job->getId());
        }

        return Command::SUCCESS;
    }
}
