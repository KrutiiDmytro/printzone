<?php

declare(strict_types=1);

namespace App\Export\Domain\Entity;

use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportStatus;
use App\Export\Enum\ExportType;
use App\Repository\ExportJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ExportJobRepository::class)]
#[ORM\Table(name: 'export_jobs', schema: 'exports')]
#[ORM\Index(columns: ['status'], name: 'idx_export_jobs_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_export_jobs_created_at')]
class ExportJob
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 50, enumType: ExportType::class)]
    private ExportType $type;

    #[ORM\Column(length: 10, enumType: ExportFormat::class)]
    private ExportFormat $format;

    #[ORM\Column(length: 20, enumType: ExportStatus::class)]
    private ExportStatus $status = ExportStatus::Pending;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $filters = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 180)]
    private string $requestedBy;

    public function __construct(ExportType $type, ExportFormat $format, string $requestedBy, ?array $filters = null)
    {
        $this->id = Uuid::v4();
        $this->type = $type;
        $this->format = $format;
        $this->requestedBy = $requestedBy;
        $this->filters = $filters;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getType(): ExportType
    {
        return $this->type;
    }

    public function getFormat(): ExportFormat
    {
        return $this->format;
    }

    public function getStatus(): ExportStatus
    {
        return $this->status;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getFilters(): ?array
    {
        return $this->filters;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getRequestedBy(): string
    {
        return $this->requestedBy;
    }

    public function markProcessing(): void
    {
        $this->status = ExportStatus::Processing;
    }

    public function markCompleted(string $filePath): void
    {
        $this->status = ExportStatus::Completed;
        $this->filePath = $filePath;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = ExportStatus::Failed;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
    }
}
