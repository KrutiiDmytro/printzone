<?php

namespace App\Tests\Functional;

use App\Entity\Cart;
use App\Entity\CartItem;
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
     * Persists a cart with one item for the given user and returns it.
     */
    protected function seedCart(Uuid $userId, Uuid $productId, int $quantity = 1): Cart
    {
        $cart = (new Cart())->setUserId($userId);
        $item = (new CartItem())
            ->setProductId($productId)
            ->setProductName('Canon PG-540 Black')
            ->setPrice(1999)
            ->setQuantity($quantity);
        $cart->addItem($item);

        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }

    protected function serviceToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    protected function adminToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-admin', null, ['ROLE_USER', 'ROLE_CART_ADMIN']));
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
