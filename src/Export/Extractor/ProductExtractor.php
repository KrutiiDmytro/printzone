<?php

declare(strict_types=1);

namespace App\Export\Extractor;

use App\Export\Client\CatalogProductClient;

/**
 * Extracts products for export from the Catalog Service over HTTP (Phase 4),
 * paginating through the service API instead of querying local Doctrine tables.
 */
final class ProductExtractor implements ExportExtractorInterface
{
    private const PAGE_SIZE = 500;

    public function __construct(private readonly CatalogProductClient $catalog)
    {
    }

    public function extract(array $filters): array
    {
        $rows = [];
        $page = 1;

        do {
            $result = $this->catalog->fetchProducts($filters, $page, self::PAGE_SIZE);
            foreach ($result['data'] as $product) {
                $rows[] = $this->toRow($product);
            }
            $fetched = count($result['data']);
            ++$page;
        } while (self::PAGE_SIZE === $fetched);

        return $rows;
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    private function toRow(array $product): array
    {
        $category = $product['category'] ?? null;

        return [
            'id' => (string) ($product['id'] ?? ''),
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => number_format(((int) ($product['price'] ?? 0)) / 100, 2),
            'stock' => $product['stock'] ?? 0,
            'is_featured' => ($product['isFeatured'] ?? false) ? 'yes' : 'no',
            'category' => is_array($category) ? ($category['name'] ?? '') : '',
            'image' => $product['image'] ?? '',
        ];
    }
}
