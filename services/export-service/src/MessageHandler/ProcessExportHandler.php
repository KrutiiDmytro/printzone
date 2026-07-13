<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Client\StorageClient;
use App\Enum\ExportFormat;
use App\Enum\ExportStatus;
use App\Enum\ExportType;
use App\Extractor\ExportExtractorInterface;
use App\Formatter\CsvFormatter;
use App\Formatter\ExportFormatterInterface;
use App\Formatter\JsonFormatter;
use App\Formatter\XmlFormatter;
use App\Message\ProcessExportMessage;
use App\Repository\ExportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ProcessExportHandler
{
    public function __construct(
        private readonly ExportJobRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly ExportExtractorInterface $productExtractor,
        private readonly ExportExtractorInterface $orderExtractor,
        private readonly ExportExtractorInterface $userExtractor,
        private readonly StorageClient $storage,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail,
    ) {
    }

    public function __invoke(ProcessExportMessage $message): void
    {
        if (!Uuid::isValid($message->exportJobId)) {
            return;
        }
        $job = $this->repository->find(Uuid::fromString($message->exportJobId));
        if (null === $job) {
            return;
        }

        // At-least-once delivery: a job already finished is a no-op.
        if (ExportStatus::Completed === $job->getStatus()) {
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
                'exports/%s/%s/%s-%s.%s',
                $job->getType()->value,
                $job->getFormat()->value,
                $job->getId(),
                $timestamp,
                $formatter->extension()
            );

            $this->storage->write($filePath, $content, $formatter->contentType());
            $job->markCompleted($filePath);
            $this->em->flush();

            $this->sendNotification($job->getRequestedBy(), (string) $job->getId(), $job->getType()->value, $job->getFormat()->value, true);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $previous = $e->getPrevious();
            while (null !== $previous) {
                $errorMessage .= ' Caused by: '.$previous->getMessage();
                $previous = $previous->getPrevious();
            }
            $job->markFailed($errorMessage);
            $this->em->flush();
            $this->sendNotification($job->getRequestedBy(), (string) $job->getId(), $job->getType()->value, $job->getFormat()->value, false, $errorMessage);
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

    private function sendNotification(string $to, string $jobId, string $type, string $format, bool $success, string $error = ''): void
    {
        $subject = $success
            ? sprintf('Експорт %s (%s) завершено', $type, $format)
            : sprintf('Помилка експорту %s (%s)', $type, $format);

        $body = $success
            ? sprintf('Ваш експорт готовий. <a href="/admin/export/download/%s">Завантажити файл</a>', $jobId)
            : sprintf('Під час експорту сталася помилка: %s', htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));

        $html = sprintf(
            '<!DOCTYPE html><html lang="uk"><body style="font-family: Arial, sans-serif; color: #333; padding: 20px;">'
            .'<h2 style="color: #2d6a9f;">%s</h2><p>%s</p>'
            .'<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">'
            .'<p style="font-size: 12px; color: #999;">Адмін-панель електронного магазину</p></body></html>',
            htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
            $body
        );

        $email = (new Email())
            ->from($this->adminEmail)
            ->to($to)
            ->subject($subject)
            ->html($html);

        // The export file is already written + the job persisted; a notification
        // failure (misconfigured mailer, transient SMTP/SES error) must never fail
        // the job or trigger a retry. Best-effort: log and move on.
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Export notification email failed', [
                'jobId' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
