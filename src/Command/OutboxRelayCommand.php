<?php

declare(strict_types=1);

namespace App\Command;

use App\Messaging\Application\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:outbox:relay',
    description: 'Publishes unpublished outbox messages to RabbitMQ (transactional outbox relay)',
)]
final class OutboxRelayCommand extends Command
{
    public function __construct(private readonly OutboxRelay $relay)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Seconds to run before exiting (0 = forever)', '0')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Poll interval in seconds when the outbox is empty', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeLimit = (int) $input->getOption('time-limit');
        $sleep = max(1, (int) $input->getOption('sleep'));
        $deadline = $timeLimit > 0 ? time() + $timeLimit : null;

        while (true) {
            $relayed = $this->relay->relayBatch();
            if ($relayed > 0) {
                $output->writeln(sprintf('[outbox] relayed %d event(s)', $relayed));
            }

            if (null !== $deadline && time() >= $deadline) {
                return Command::SUCCESS;
            }

            sleep($sleep);
        }
    }
}
