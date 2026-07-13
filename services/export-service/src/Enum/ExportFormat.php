<?php

declare(strict_types=1);

namespace App\Enum;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Json = 'json';
    case Xml = 'xml';
}
