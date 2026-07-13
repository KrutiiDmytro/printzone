<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessExportMessage
{
    public function __construct(public string $exportJobId)
    {
    }
}
