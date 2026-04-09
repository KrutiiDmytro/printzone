<?php

declare(strict_types=1);

namespace App\Command;

use App\Storage\FileStorageInterface;
use Aws\S3\S3Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:verify-storage',
    description: 'Перевіряє STORAGE_TYPE, змінні AWS/S3 та доступ до бакета (HeadBucket + префікс products/)'
)]
final class VerifyStorageCommand extends Command
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly FileStorageInterface $storage,
        #[Autowire('%env(STORAGE_TYPE)%')]
        private readonly string $storageType,
        #[Autowire('%aws.s3.bucket%')]
        private readonly string $bucket,
        #[Autowire('%env(AWS_DEFAULT_REGION)%')]
        private readonly string $region,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = strtolower(trim($this->storageType));
        $keyId = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID') ?: '';

        $io->title('Перевірка сховища (Task-22)');

        $io->definitionList(
            ['STORAGE_TYPE' => $type],
            ['AWS_DEFAULT_REGION' => $this->region],
            ['AWS_S3_BUCKET' => '' !== $this->bucket ? $this->bucket : '(порожньо — задайте в .env.local)'],
            ['AWS_ACCESS_KEY_ID' => '' !== trim((string) $keyId) ? 'задано' : '(порожньо)'],
        );

        if ('' === $this->bucket && 's3' === $type) {
            $io->error('Для STORAGE_TYPE=s3 потрібен AWS_S3_BUCKET у .env.local');

            return Command::FAILURE;
        }

        if ('s3' === $type) {
            return $this->verifyS3($io);
        }

        $io->section('Режим local');
        try {
            $keys = $this->storage->listKeys('products/', true);
            $io->success(\sprintf('Локальне сховище: знайдено %d файл(ів) під префіксом products/', \count($keys)));
            if (\count($keys) > 0) {
                $io->listing(array_slice($keys, 0, 15));
                if (\count($keys) > 15) {
                    $io->note('Показано перші 15 ключів.');
                }
            } else {
                $io->note('Файлів з префіксом products/ немає — завантажте зображення товару в адмінці (STORAGE_TYPE=local).');
            }
        } catch (\Throwable $e) {
            $io->error('Помилка читання локального сховища: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->note('У консолі S3 нічого шукати не потрібно — використовується лише диск (var/storage).');

        return Command::SUCCESS;
    }

    private function verifyS3(SymfonyStyle $io): int
    {
        $io->section('Режим S3');

        try {
            $this->s3Client->headBucket(['Bucket' => $this->bucket]);
            $io->success(\sprintf('Бакет "%s" доступний у регіоні %s (HeadBucket OK).', $this->bucket, $this->region));
        } catch (\Throwable $e) {
            $io->error('HeadBucket не вдався: '.$e->getMessage());
            $io->note('Перевірте: ім\'я бакета, регіон (AWS_DEFAULT_REGION = регіон створення бакета), ключі IAM, час системи.');
            $io->note('Якщо був PermanentRedirect — регіон клієнта не збігається з регіоном бакета.');

            return Command::FAILURE;
        }

        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => 'products/',
                'MaxKeys' => 20,
            ]);
            $contents = $result->get('Contents') ?? [];
            $count = \count($contents);
            if (0 === $count) {
                $io->warning('У бакеті за префіксом products/ об\'єктів не знайдено (або ще не завантажували).');
                $io->note('Після успішного presigned upload у консолі S3 → Objects з\'являться ключі на кшталт products/<uuid>-file.png');
            } else {
                $io->success(\sprintf('Знайдено об\'єктів з префіксом products/: %d (показано до 20).', $count));
                foreach ($contents as $obj) {
                    $key = $obj['Key'] ?? '';
                    if ('' !== $key) {
                        $io->text(' • '.$key);
                    }
                }
            }
        } catch (\Throwable $e) {
            $io->error('ListObjectsV2: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->note('Переконайтеся, що в AWS Console відкритий той самий бакет і регіон, що й AWS_DEFAULT_REGION у .env.local.');

        return Command::SUCCESS;
    }
}
