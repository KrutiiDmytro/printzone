<?php

declare(strict_types=1);

namespace App\Export\Extractor;

interface ExportExtractorInterface
{
    /** @return array<int, array<string, mixed>> */
    public function extract(array $filters): array;
}
