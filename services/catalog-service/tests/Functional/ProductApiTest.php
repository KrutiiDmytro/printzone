<?php

namespace App\Tests\Functional;

class ProductApiTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListReturnsProducts(): void
    {
        $this->authGet('/api/products');

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame(3, $data['total']);
        self::assertCount(3, $data['data']);
        self::assertArrayHasKey('category', $data['data'][0]);
        self::assertArrayHasKey('brand', $data['data'][0]);
    }

    public function testFeaturedFilter(): void
    {
        $this->authGet('/api/products?isFeatured=1');

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame(1, $data['total']);
        self::assertSame('Canon PG-540 Black', $data['data'][0]['name']);
    }

    public function testPaginationLimitsResults(): void
    {
        $this->authGet('/api/products?limit=2&page=1');

        $data = $this->json();
        self::assertCount(2, $data['data']);
        self::assertSame(3, $data['total']);
    }

    public function testGetByIdReturnsProduct(): void
    {
        $this->authGet('/api/products');
        $id = $this->json()['data'][0]['id'];

        $this->authGet('/api/products/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame($id, $this->json()['id']);
    }

    public function testGetByIdNotFound(): void
    {
        $this->authGet('/api/products/'.\Symfony\Component\Uid\Uuid::v4());

        self::assertResponseStatusCodeSame(404);
    }
}
