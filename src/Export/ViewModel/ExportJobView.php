<?php

declare(strict_types=1);

namespace App\Export\ViewModel;

use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportStatus;
use App\Export\Enum\ExportType;

/**
 * Read model for the admin export index — reconstructs the job shape the Twig
 * template expects (enum-typed type/format/status, a DateTimeImmutable) from the
 * JSON the Export Service returns.
 */
final class ExportJobView
{
    public function __construct(
        public readonly string $id,
        public readonly ExportType $type,
        public readonly ExportFormat $format,
        public readonly ExportStatus $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?string $errorMessage,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            ExportType::from((string) $row['type']),
            ExportFormat::from((string) $row['format']),
            ExportStatus::from((string) $row['status']),
            new \DateTimeImmutable((string) ($row['createdAt'] ?? 'now')),
            isset($row['errorMessage']) ? (string) $row['errorMessage'] : null,
        );
    }
}
