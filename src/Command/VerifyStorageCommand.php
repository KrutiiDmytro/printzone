<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:verify-storage',
    description: 'Перевіряє доступність Storage Service через /health/ready (S3/local — на боці сервісу)'
)]
final class VerifyStorageCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(STORAGE_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = rtrim($this->baseUrl, '/').'/health/ready';

        $io->title('Перевірка Storage Service');
        $io->definitionList(['STORAGE_SERVICE_URL' => $this->baseUrl], ['endpoint' => $url]);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $io->error('Не вдалося звернутися до Storage Service: '.$e->getMessage());
            $io->note('Перевірте, що сервіс піднятий і STORAGE_SERVICE_URL вказує на нього.');

            return Command::FAILURE;
        }

        if (200 === $status && 'ok' === ($data['status'] ?? null)) {
            $io->success('Storage Service готовий (/health/ready → 200 ok).');

            return Command::SUCCESS;
        }

        $io->error(\sprintf('Storage Service не готовий (HTTP %d).', $status));
        $detail = $data['checks']['storage']['detail'] ?? null;
        if (null !== $detail) {
            $io->note('Деталі: '.$detail);
        }

        return Command::FAILURE;
    }
}
