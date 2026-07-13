<?php

declare(strict_types=1);

namespace App\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Writes generated export files to the Storage Service over S2S HTTP. Only the
 * write path is needed here — the monolith admin reads results back with its own
 * StorageClient. Signs a ROLE_STORAGE_ADMIN token (writes are elevated).
 */
class StorageClient
{
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
        $options = ['body' => $contents, 'auth_bearer' => $this->token(), 'timeout' => 15];
        if (null !== $contentType) {
            $options['headers'] = ['Content-Type' => $contentType];
        }

        try {
            $response = $this->httpClient->request('PUT', $this->url('/api/storage/objects/'.$this->encodeKey($key)), $options);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new \RuntimeException(sprintf('Storage Service returned HTTP %d for PUT %s', $status, $key));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Storage Service write failed', ['key' => $key, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Storage Service write failed', 0, $e);
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
        return $this->jwtManager->create(
            new InMemoryUser('service-storage', null, ['ROLE_USER', 'ROLE_STORAGE_ADMIN'])
        );
    }
}
