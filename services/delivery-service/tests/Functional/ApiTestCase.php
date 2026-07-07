<?php

namespace App\Tests\Functional;

use App\Entity\Shipment;
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
        if ([] !== $metadata) {
            $schemaTool = new SchemaTool($this->em);
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }
    }

    protected function seedShipment(string $trackingNumber = 'FAKE_SEED_1', ?Uuid $orderId = null): Shipment
    {
        $shipment = new Shipment($orderId ?? Uuid::v4(), 'fake', [
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'city' => 'Kyiv',
        ]);
        $shipment->setTrackingNumber($trackingNumber);

        $this->em->persist($shipment);
        $this->em->flush();

        return $shipment;
    }

    /** A non-privileged service token (read-level). */
    protected function serviceToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    /** A service token allowed to write shipments (admin/webhook-driven). */
    protected function deliveryAdminToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-delivery', null, ['ROLE_USER', 'ROLE_DELIVERY_ADMIN']));
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
