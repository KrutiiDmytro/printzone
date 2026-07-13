<?php

declare(strict_types=1);

namespace App\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads products from the Catalog Service over HTTP (paginated). Signs a short
 * service JWT with the shared keypair; the Catalog Service verifies it.
 */
class CatalogProductClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(CATALOG_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function fetchProducts(array $filters, int $page, int $limit): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').'/api/products', [
            'query' => array_merge($filters, ['page' => $page, 'limit' => $limit]),
            'auth_bearer' => $this->serviceToken(),
            'timeout' => 5,
        ]);

        /** @var array{data?: array<int, array<string, mixed>>, total?: int} $payload */
        $payload = $response->toArray();

        return [
            'data' => $payload['data'] ?? [],
            'total' => $payload['total'] ?? 0,
        ];
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-export', null, ['ROLE_USER']));
    }
}
