<?php

declare(strict_types=1);

namespace App\Export\Formatter;

final class JsonFormatter implements ExportFormatterInterface
{
    public function format(array $rows): string
    {
        return (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function extension(): string
    {
        return 'json';
    }
}
