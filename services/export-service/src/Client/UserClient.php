<?php

declare(strict_types=1);

namespace App\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads users from the User Service over HTTP for export. The endpoint requires
 * ROLE_ADMIN and returns every user unfiltered/unpaginated ({id,email,fullName,
 * roles[]}); email/role filtering happens locally in UserExtractor.
 */
class UserClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(USER_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array<int, array{id: string, email: string, fullName: string|null, roles: list<string>}>
     */
    public function fetchAll(): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/').'/api/users', [
            'auth_bearer' => $this->serviceToken(),
            'timeout' => 5,
        ]);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException(sprintf('User Service returned %d.', $status));
        }

        return $response->toArray(false);
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-export', null, ['ROLE_USER', 'ROLE_ADMIN']));
    }
}
