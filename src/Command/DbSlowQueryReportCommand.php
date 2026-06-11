<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:slow-queries',
    description: 'Show top slow queries from pg_stat_statements',
)]
class DbSlowQueryReportCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Number of queries to show', 20)
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset pg_stat_statements statistics after display');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->isPostgres()) {
            $io->warning('pg_stat_statements is only available on PostgreSQL. Skipping.');

            return Command::SUCCESS;
        }

        $limit = (int) $input->getOption('limit');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                LEFT(query, 120)          AS query,
                calls,
                ROUND(total_exec_time::numeric, 2) AS total_ms,
                ROUND(mean_exec_time::numeric, 2)  AS mean_ms,
                rows
             FROM pg_stat_statements
             ORDER BY mean_exec_time DESC
             LIMIT :limit',
            ['limit' => $limit]
        );

        if (empty($rows)) {
            $io->note('No query statistics found. Run some queries first.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Top %d slowest queries (mean execution time)', count($rows)));

        $table = new Table($output);
        $table->setHeaders(['Query', 'Calls', 'Mean ms', 'Total ms', 'Rows']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['query'],
                $row['calls'],
                $row['mean_ms'],
                $row['total_ms'],
                $row['rows'],
            ]);
        }
        $table->render();

        if ($input->getOption('reset')) {
            $this->connection->executeStatement('SELECT pg_stat_statements_reset()');
            $io->success('Statistics reset.');
        }

        return Command::SUCCESS;
    }

    private function isPostgres(): bool
    {
        try {
            $this->connection->fetchOne('SELECT 1 FROM pg_stat_statements LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
