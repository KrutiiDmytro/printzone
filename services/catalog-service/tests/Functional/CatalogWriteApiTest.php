<?php

namespace App\Tests\Functional;

use Symfony\Component\Uid\Uuid;

class CatalogWriteApiTest extends ApiTestCase
{
    private function categoryId(): string
    {
        $this->authGet('/api/categories');

        return $this->json()['data'][0]['id'];
    }

    private function brandId(): string
    {
        $this->authGet('/api/brands');

        return $this->json()['data'][0]['id'];
    }

    public function testWriteRequiresAuthentication(): void
    {
        $this->send('POST', '/api/products', ['name' => 'X']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testWriteRejectsReadOnlyToken(): void
    {
        $this->send('POST', '/api/products', ['name' => 'X'], $this->serviceToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateProduct(): void
    {
        $this->send('POST', '/api/products', [
            'name' => 'New Toner',
            'price' => 4200,
            'stock' => 7,
            'category' => $this->categoryId(),
            'brand' => $this->brandId(),
            'isFeatured' => true,
        ], $this->adminToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('New Toner', $body['name']);
        self::assertSame(4200, $body['price']);
        self::assertTrue($body['isFeatured']);
        self::assertNotNull($body['brand']);

        // Round-trip: it is now readable.
        $this->authGet('/api/products/'.$body['id']);
        self::assertResponseIsSuccessful();
    }

    public function testCreateProductValidationFails(): void
    {
        $this->send('POST', '/api/products', [
            'name' => '',
            'price' => -1,
            'category' => $this->categoryId(),
        ], $this->adminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateProductUnknownCategory(): void
    {
        $this->send('POST', '/api/products', [
            'name' => 'Orphan',
            'price' => 100,
            'stock' => 1,
            'category' => (string) Uuid::v4(),
        ], $this->adminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateProduct(): void
    {
        $this->authGet('/api/products');
        $id = $this->json()['data'][0]['id'];

        $this->send('PATCH', '/api/products/'.$id, ['price' => 9999, 'stock' => 0], $this->adminToken());

        self::assertResponseIsSuccessful();
        self::assertSame(9999, $this->json()['price']);
        self::assertSame(0, $this->json()['stock']);
    }

    public function testUpdateProductNotFound(): void
    {
        $this->send('PATCH', '/api/products/'.Uuid::v4(), ['price' => 1], $this->adminToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteProduct(): void
    {
        $this->authGet('/api/products');
        $id = $this->json()['data'][0]['id'];

        $this->send('DELETE', '/api/products/'.$id, [], $this->adminToken());
        self::assertResponseStatusCodeSame(204);

        $this->authGet('/api/products/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateCategory(): void
    {
        $this->send('POST', '/api/categories', ['name' => 'Ribbons', 'slug' => 'ribbons'], $this->adminToken());

        self::assertResponseStatusCodeSame(201);
        self::assertSame('ribbons', $this->json()['slug']);
    }

    public function testCreateCategoryDuplicateSlug(): void
    {
        $this->send('POST', '/api/categories', ['name' => 'Dup', 'slug' => 'cartridges'], $this->adminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateAndDeleteBrand(): void
    {
        $this->send('POST', '/api/brands', ['name' => 'Epson', 'slug' => 'epson', 'color' => '#003399'], $this->adminToken());
        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'];

        $this->send('DELETE', '/api/brands/'.$id, [], $this->adminToken());
        self::assertResponseStatusCodeSame(204);
    }

    public function testCreateBrandInvalidColor(): void
    {
        $this->send('POST', '/api/brands', ['name' => 'Bad', 'slug' => 'bad', 'color' => 'red'], $this->adminToken());

        self::assertResponseStatusCodeSame(422);
    }
}
