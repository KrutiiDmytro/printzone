<?php

declare(strict_types=1);

namespace App\Export\Formatter;

interface ExportFormatterInterface
{
    public function format(array $rows): string;

    public function contentType(): string;

    public function extension(): string;
}
