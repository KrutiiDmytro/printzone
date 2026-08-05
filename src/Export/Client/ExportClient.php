<?php

declare(strict_types=1);

namespace App\Export\Client;

use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Export Service — the monolith admin is now a thin proxy over it.
 * `create` is strict (throws → the controller shows a danger flash); `listRecent`
 * degrades to an empty list so the admin page still renders if the service is
 * briefly down; `get` returns null when the job is missing.
 */
class ExportClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(EXPORT_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function create(ExportType $type, ExportFormat $format, string $requestedBy, array $filters = []): array
    {
        return $this->request('POST', '/api/exports', [
            'type' => $type->value,
            'format' => $format->value,
            'requestedBy' => $requestedBy,
            'filters' => (object) $filters,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(): array
    {
        try {
            return $this->request('GET', '/api/exports')['data'] ?? [];
        } catch (\Throwable) {
            // Reads degrade so the admin export page stays up if the service is down.
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        try {
            $row = $this->request('GET', '/api/exports/'.$id);

            return isset($row['id']) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $options = ['auth_bearer' => $this->serviceToken(), 'timeout' => 5];
            if (null !== $body) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                throw new \RuntimeException(sprintf('Export Service returned HTTP %d for %s %s', $status, $method, $path));
            }

            return 204 === $status ? [] : $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('Export Service request failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Export Service request failed', 0, $e);
        }
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-export', null, ['ROLE_USER', 'ROLE_EXPORT_ADMIN']));
    }
}
