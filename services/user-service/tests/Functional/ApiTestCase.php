<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $seed = [
            ['admin@example.com', 'admin123', ['ROLE_ADMIN'], 'Admin User'],
            ['user@example.com', 'user1234', ['ROLE_USER'], 'Regular User'],
        ];
        foreach ($seed as [$email, $password, $roles, $name]) {
            $user = new User();
            $user->setEmail($email);
            $user->setFullName($name);
            $user->setRoles($roles);
            $user->setPassword($hasher->hashPassword($user, $password));
            $this->em->persist($user);
        }
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function jsonRequest(string $method, string $uri, array $body = [], ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request($method, $uri, [], [], $server, $body ? json_encode($body) : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }

    protected function login(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);

        return $this->json()['token'] ?? '';
    }

    protected function userId(string $email): string
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return (string) $user->getId();
    }
}
