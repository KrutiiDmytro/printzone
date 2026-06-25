<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Uid\Uuid;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * Persists an order with one item and returns it.
     */
    protected function seedOrder(string $status = 'PENDING'): Order
    {
        $order = new Order();
        $order->setUserId(Uuid::v4());
        $order->setUserEmail('buyer@example.com');
        $order->setStatus($status);
        $order->setTotalAmount(1999);

        $item = new OrderItem();
        $item->setOrderRef($order);
        $item->setProductId(Uuid::v4());
        $item->setProductName('Canon PG-540 Black');
        $item->setPrice(1999);
        $item->setQuantity(1);
        $order->getItems()->add($item);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    protected function serviceToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    protected function adminToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-admin', null, ['ROLE_USER', 'ROLE_ADMIN']));
    }

    /**
     * Builds a valid Stripe-Signature header for the given payload using the
     * test webhook secret, so the real signature verification path is exercised.
     */
    protected function stripeSignature(string $payload, string $secret = 'whsec_test_dummy'): string
    {
        // Current time so the signature stays inside Stripe's default tolerance.
        $timestamp = time();
        $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return sprintf('t=%d,v1=%s', $timestamp, $signed);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function send(string $method, string $uri, array $body = [], ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, [], [], $server, [] === $body ? null : (string) json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}
