<?php

declare(strict_types=1);

namespace App\Storage\Client;

use App\Storage\FileStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the Storage Service over S2S HTTP, implementing the same
 * FileStorageInterface the monolith used locally — so every consumer
 * (MediaController, ProductImageService, Export handler/controller) keeps
 * working unchanged after the storage.yaml rebind.
 *
 * Strict by design: failures throw (matching the old Flysystem backend). The
 * few callers that want to degrade (image display, safe delete) already catch.
 */
class StorageClient implements FileStorageInterface
{
    /** Per-request service JWT (ROLE_STORAGE_ADMIN covers reads and writes). */
    private ?string $serviceToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(STORAGE_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    public function write(string $key, string $contents, ?string $contentType = null): void
    {
        $options = ['body' => $contents];
        if (null !== $contentType) {
            $options['headers'] = ['Content-Type' => $contentType];
        }
        $this->request('PUT', '/api/storage/objects/'.$this->encodeKey($key), $options);
    }

    public function read(string $key): string
    {
        return $this->request('GET', '/api/storage/objects/'.$this->encodeKey($key))->getContent();
    }

    public function delete(string $key): void
    {
        $this->request('DELETE', '/api/storage/objects/'.$this->encodeKey($key));
    }

    public function exists(string $key): bool
    {
        try {
            $status = $this->httpClient
                ->request('HEAD', $this->url('/api/storage/objects/'.$this->encodeKey($key)), [
                    'auth_bearer' => $this->token(),
                    'timeout' => 5,
                ])
                ->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('Storage Service HEAD failed', ['key' => $key, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Storage Service request failed', 0, $e);
        }

        if (404 === $status) {
            return false;
        }
        if ($status >= 200 && $status < 300) {
            return true;
        }

        throw new \RuntimeException(sprintf('Storage Service returned HTTP %d for HEAD %s', $status, $key));
    }

    public function listKeys(string $prefix = '', bool $deep = false): array
    {
        $data = $this->request('GET', '/api/storage/objects', [
            'query' => ['prefix' => $prefix, 'deep' => $deep ? '1' : '0'],
        ])->toArray(false);

        /** @var list<string> $keys */
        $keys = $data['keys'] ?? [];

        return $keys;
    }

    public function publicUrl(string $key): ?string
    {
        $data = $this->request('GET', '/api/storage/presign-get', ['query' => ['key' => $key]])->toArray(false);

        return $data['url'] ?? null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        try {
            $options['auth_bearer'] = $this->token();
            $options['timeout'] ??= 10;

            $response = $this->httpClient->request($method, $this->url($path), $options);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                throw new \RuntimeException(sprintf('Storage Service returned HTTP %d for %s %s', $status, $method, $path));
            }

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Storage Service request failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Storage Service request failed', 0, $e);
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }

    /** Keep slashes as path separators; encode each segment's own characters. */
    private function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function token(): string
    {
        return $this->serviceToken ??= $this->jwtManager->create(
            new InMemoryUser('service-storage', null, ['ROLE_USER', 'ROLE_STORAGE_ADMIN'])
        );
    }
}
