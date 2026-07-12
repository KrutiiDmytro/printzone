<?php

namespace App\Tests\Functional;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    private function jwt(): JWTTokenManagerInterface
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class);
    }

    /** Any valid service token (reads). */
    protected function serviceToken(): string
    {
        return $this->jwt()->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    /** Elevated service token (writes: presign PUT, object write/delete). */
    protected function adminToken(): string
    {
        return $this->jwt()->create(new InMemoryUser('service-admin', null, ['ROLE_USER', 'ROLE_STORAGE_ADMIN']));
    }

    /**
     * @param array<string, mixed> $json
     */
    protected function send(string $method, string $uri, ?string $token = null, array $json = []): void
    {
        $server = [];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if ([] !== $json) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request($method, $uri, [], [], $server, [] === $json ? null : (string) json_encode($json));
    }

    protected function put(string $uri, string $body, string $contentType, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => $contentType];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request('PUT', $uri, [], [], $server, $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}
