<?php

declare(strict_types=1);

namespace App\Command;

use App\Delivery\TrackingRecorder;
use App\Entity\Shipment;
use App\Repository\ShipmentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Pushes a tracking status onto a shipment without a real carrier, exercising the
 * exact same append-only path as the Nova Poshta webhook. Demonstrates the
 * Event Sourcing log (and lets you drive an order to DELIVERED in dev).
 */
#[AsCommand(
    name: 'app:delivery:simulate-tracking',
    description: 'Appends a tracking status to a shipment (demo of the event-sourced tracking log)',
)]
final class SimulateTrackingCommand extends Command
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly TrackingRecorder $tracking,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('shipmentId', InputArgument::REQUIRED, 'Shipment UUID')
            ->addArgument('status', InputArgument::REQUIRED, 'One of: '.implode(', ', Shipment::STATUSES))
            ->addOption('location', null, InputOption::VALUE_REQUIRED, 'Location label')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Free-text description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $shipmentId = (string) $input->getArgument('shipmentId');
        $status = (string) $input->getArgument('status');

        if (!Uuid::isValid($shipmentId)) {
            $io->error('Invalid shipment UUID.');

            return Command::INVALID;
        }
        if (!in_array($status, Shipment::STATUSES, true)) {
            $io->error('Invalid status. Allowed: '.implode(', ', Shipment::STATUSES));

            return Command::INVALID;
        }

        $shipment = $this->shipments->find(Uuid::fromString($shipmentId));
        if (null === $shipment) {
            $io->error('Shipment not found.');

            return Command::FAILURE;
        }

        $applied = $this->tracking->record(
            $shipment,
            $status,
            $input->getOption('location'),
            $input->getOption('description'),
        );

        $io->success(sprintf('%s → %s (%s)', $shipmentId, $status, $applied ? 'appended' : 'duplicate, skipped'));

        return Command::SUCCESS;
    }
}
