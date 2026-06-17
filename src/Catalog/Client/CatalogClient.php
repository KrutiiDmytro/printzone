<?php

declare(strict_types=1);

namespace App\Catalog\Client;

use App\Catalog\View\BrandView;
use App\Catalog\View\CategoryView;
use App\Catalog\View\ProductView;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Storefront-facing client for the Catalog Service. Returns view DTOs shaped like
 * the old Doctrine entities so templates work unchanged. Calls are resilient:
 * on a service error the navbar/listing degrades to empty rather than 500ing.
 */
class CatalogClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CATALOG_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: ProductView[], priceRange: array{min: int, max: int}, total: int}
     */
    public function products(array $query): array
    {
        $payload = $this->get('/api/products', array_merge(['availableOnly' => 1, 'limit' => 500], $query));

        $items = array_map(ProductView::fromArray(...), $payload['data'] ?? []);
        $range = $payload['priceRange'] ?? ['min' => 0, 'max' => 0];

        return [
            'items' => $items,
            'priceRange' => ['min' => (int) ($range['min'] ?? 0), 'max' => (int) ($range['max'] ?? 0)],
            'total' => (int) ($payload['total'] ?? count($items)),
        ];
    }

    /**
     * @return ProductView[]
     */
    public function featured(int $limit = 12): array
    {
        $payload = $this->get('/api/products', ['isFeatured' => 1, 'availableOnly' => 1, 'limit' => $limit]);

        return array_map(ProductView::fromArray(...), $payload['data'] ?? []);
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, ProductView> keyed by product id
     */
    public function productsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $payload = $this->get('/api/products', ['ids' => implode(',', $ids)]);

        $map = [];
        foreach ($payload['data'] ?? [] as $row) {
            $view = ProductView::fromArray($row);
            $map[$view->getId()] = $view;
        }

        return $map;
    }

    public function product(string $id): ?ProductView
    {
        $payload = $this->get('/api/products/'.$id);

        return isset($payload['id']) ? ProductView::fromArray($payload) : null;
    }

    /**
     * @return CategoryView[]
     */
    public function rootCategories(): array
    {
        $payload = $this->get('/api/categories', ['root' => 1]);

        return array_map(CategoryView::fromArray(...), $payload['data'] ?? []);
    }

    public function category(string $slug): ?CategoryView
    {
        $payload = $this->get('/api/categories/'.$slug);

        return isset($payload['id']) ? CategoryView::fromArray($payload) : null;
    }

    /**
     * @return BrandView[]
     */
    public function brands(): array
    {
        $payload = $this->get('/api/brands');

        return array_map(BrandView::fromArray(...), $payload['data'] ?? []);
    }

    public function brand(string $slug): ?BrandView
    {
        $payload = $this->get('/api/brands/'.$slug);

        return isset($payload['id']) ? BrandView::fromArray($payload) : null;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            return $this->httpClient->request('GET', rtrim($this->baseUrl, '/').$path, [
                'query' => $query,
                'auth_bearer' => $this->serviceToken(),
                'timeout' => 5,
            ])->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('Catalog Service request failed', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-storefront', null, ['ROLE_USER']));
    }
}
