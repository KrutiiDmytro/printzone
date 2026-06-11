<?php

declare(strict_types=1);

namespace App\Export\MessageHandler;

use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use App\Export\Extractor\ExportExtractorInterface;
use App\Export\Formatter\CsvFormatter;
use App\Export\Formatter\ExportFormatterInterface;
use App\Export\Formatter\JsonFormatter;
use App\Export\Formatter\XmlFormatter;
use App\Export\Message\ProcessExportMessage;
use App\Repository\ExportJobRepository;
use App\Storage\FileStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessExportHandler
{
    public function __construct(
        private readonly ExportJobRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly ExportExtractorInterface $productExtractor,
        private readonly ExportExtractorInterface $orderExtractor,
        private readonly ExportExtractorInterface $userExtractor,
        private readonly FileStorageInterface $storage,
        private readonly MailerInterface $mailer,
        private readonly string $adminEmail,
    ) {
    }

    public function __invoke(ProcessExportMessage $message): void
    {
        $job = $this->repository->find($message->exportJobId);
        if (null === $job) {
            return;
        }

        $job->markProcessing();
        $this->em->flush();

        try {
            $filters = $job->getFilters() ?? [];

            $rows = match ($job->getType()) {
                ExportType::Products => $this->productExtractor->extract($filters),
                ExportType::Orders => $this->orderExtractor->extract($filters),
                ExportType::Users => $this->userExtractor->extract($filters),
            };

            $formatter = $this->resolveFormatter($job->getFormat());
            $content = $formatter->format($rows);

            $timestamp = (new \DateTimeImmutable())->format('Ymd-His');
            $filePath = sprintf(
                'exports/%s/%s/%d-%s.%s',
                $job->getType()->value,
                $job->getFormat()->value,
                $job->getId(),
                $timestamp,
                $formatter->extension()
            );

            $this->storage->write($filePath, $content, $formatter->contentType());
            $job->markCompleted($filePath);
            $this->em->flush();

            $this->sendNotification($job->getRequestedBy(), $job->getId(), $job->getType()->value, $job->getFormat()->value, true);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $previous = $e->getPrevious();
            while (null !== $previous) {
                $errorMessage .= ' Caused by: '.$previous->getMessage();
                $previous = $previous->getPrevious();
            }
            $job->markFailed($errorMessage);
            $this->em->flush();
            $this->sendNotification($job->getRequestedBy(), $job->getId(), $job->getType()->value, $job->getFormat()->value, false, $errorMessage);
        }
    }

    private function resolveFormatter(ExportFormat $format): ExportFormatterInterface
    {
        return match ($format) {
            ExportFormat::Csv => new CsvFormatter(),
            ExportFormat::Json => new JsonFormatter(),
            ExportFormat::Xml => new XmlFormatter(),
        };
    }

    private function sendNotification(string $to, ?int $jobId, string $type, string $format, bool $success, string $error = ''): void
    {
        $subject = $success
            ? sprintf('Експорт %s (%s) завершено', $type, $format)
            : sprintf('Помилка експорту %s (%s)', $type, $format);

        $message = $success
            ? sprintf('Ваш експорт готовий. <a href="/admin/export/download/%d">Завантажити файл</a>', $jobId)
            : sprintf('Під час експорту сталася помилка: %s', htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));

        $email = (new TemplatedEmail())
            ->from($this->adminEmail)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('admin/export/email.html.twig')
            ->context(['subject' => $subject, 'message' => $message]);

        $this->mailer->send($email);
    }
}
