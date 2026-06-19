<?php

declare(strict_types=1);

namespace App\Catalog\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Admin-facing client for the Catalog Service. Unlike the storefront CatalogClient
 * (read-only, graceful-empty), this one mints an elevated ROLE_CATALOG_ADMIN token
 * and surfaces failures (throws) so the admin sees write errors. Reads return raw
 * rows (incl. out-of-stock) for the admin lists/forms.
 */
class CatalogAdminClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(CATALOG_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function products(): array
    {
        return $this->request('GET', '/api/products?availableOnly=0&limit=500')['data'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(string $id): ?array
    {
        $row = $this->request('GET', '/api/products/'.$id);

        return isset($row['id']) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->request('GET', '/api/categories')['data'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function brands(): array
    {
        return $this->request('GET', '/api/brands')['data'] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function create(string $resource, array $data): array
    {
        return $this->request('POST', '/api/'.$resource, $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function update(string $resource, string $id, array $data): array
    {
        return $this->request('PUT', '/api/'.$resource.'/'.$id, $data);
    }

    public function delete(string $resource, string $id): void
    {
        $this->request('DELETE', '/api/'.$resource.'/'.$id);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $options = [
            'auth_bearer' => $this->adminToken(),
            'timeout' => 5,
        ];
        if (null !== $body) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $payload = $response->toArray(false);
            throw new \RuntimeException($payload['error'] ?? sprintf('Catalog Service returned %d.', $status));
        }

        return 204 === $status ? [] : $response->toArray(false);
    }

    private function adminToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-catalog-admin', null, ['ROLE_CATALOG_ADMIN']));
    }
}
