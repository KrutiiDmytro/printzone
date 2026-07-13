<?php

declare(strict_types=1);

namespace App\Formatter;

final class CsvFormatter implements ExportFormatterInterface
{
    public function format(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    public function contentType(): string
    {
        return 'text/csv';
    }

    public function extension(): string
    {
        return 'csv';
    }
}
