<?php

declare(strict_types=1);

namespace App\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads order rows from the Order Service over HTTP for export. Failures surface
 * (throw) so the export handler can mark the job failed.
 */
class OrderClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(ORDER_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array{status?: string, dateFrom?: string, dateTo?: string} $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $query = http_build_query(array_filter($filters, static fn ($v): bool => '' !== (string) $v));

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').'/api/orders'.($query ? '?'.$query : ''), [
            'auth_bearer' => $this->serviceToken(),
            'timeout' => 5,
        ]);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $payload = $response->toArray(false);
            throw new \RuntimeException($payload['error'] ?? sprintf('Order Service returned %d.', $status));
        }

        return $response->toArray(false)['data'] ?? [];
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-order', null, ['ROLE_USER', 'ROLE_ADMIN']));
    }
}
