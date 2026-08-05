<?php

declare(strict_types=1);

namespace App\Extractor;

use App\Client\OrderClient;

final class OrderExtractor implements ExportExtractorInterface
{
    public function __construct(private readonly OrderClient $orderClient)
    {
    }

    public function extract(array $filters): array
    {
        $rows = $this->orderClient->list([
            'status' => (string) ($filters['status'] ?? ''),
            'dateFrom' => (string) ($filters['dateFrom'] ?? ''),
            'dateTo' => (string) ($filters['dateTo'] ?? ''),
        ]);

        return array_map(static function (array $row): array {
            $createdAt = (string) ($row['createdAt'] ?? '');

            return [
                'id' => (string) ($row['id'] ?? ''),
                'user_email' => (string) ($row['userEmail'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'total_amount' => number_format((int) ($row['totalAmount'] ?? 0) / 100, 2),
                'created_at' => '' !== $createdAt
                    ? (new \DateTimeImmutable($createdAt))->format('Y-m-d H:i:s')
                    : '',
            ];
        }, $rows);
    }
}
