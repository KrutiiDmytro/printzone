<?php

namespace App\Tests\Functional;

use Symfony\Component\Uid\Uuid;

class CartApiTest extends ApiTestCase
{
    public function testGetRequiresAuthentication(): void
    {
        $this->send('GET', '/api/carts/'.Uuid::v4());

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetReturnsEmptyCartForUnknownUser(): void
    {
        $this->send('GET', '/api/carts/'.Uuid::v4(), token: $this->serviceToken());

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['items']);
    }

    public function testGetReturnsSeededCart(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $this->seedCart($userId, $productId, 2);

        $this->send('GET', '/api/carts/'.$userId, token: $this->serviceToken());

        self::assertResponseIsSuccessful();
        $items = $this->json()['items'];
        self::assertCount(1, $items);
        self::assertSame($productId->toRfc4122(), $items[0]['productId']);
        self::assertSame(2, $items[0]['quantity']);
        self::assertSame(1999, $items[0]['price']);
    }

    public function testAddItemRequiresAdminRole(): void
    {
        $this->send('POST', '/api/carts/'.Uuid::v4().'/items', [
            'productId' => (string) Uuid::v4(),
            'productName' => 'Ink',
            'price' => 1000,
            'quantity' => 1,
        ], $this->serviceToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAddItemCreatesCartAndUpsertsQuantity(): void
    {
        $userId = Uuid::v4();
        $productId = (string) Uuid::v4();
        $payload = ['productId' => $productId, 'productName' => 'Ink', 'price' => 1000, 'quantity' => 1];

        $this->send('POST', '/api/carts/'.$userId.'/items', $payload, $this->adminToken());
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['items'][0]['quantity']);

        // Second add of the same product sums the quantity.
        $this->send('POST', '/api/carts/'.$userId.'/items', ['productId' => $productId, 'productName' => 'Ink', 'price' => 1000, 'quantity' => 2], $this->adminToken());
        self::assertResponseIsSuccessful();
        $items = $this->json()['items'];
        self::assertCount(1, $items);
        self::assertSame(3, $items[0]['quantity']);
    }

    public function testAddItemRejectsInvalidPayload(): void
    {
        $this->send('POST', '/api/carts/'.Uuid::v4().'/items', [
            'productId' => 'not-a-uuid',
            'productName' => '',
            'price' => -5,
            'quantity' => 0,
        ], $this->adminToken());

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateItemSetsQuantityAndZeroRemoves(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $this->seedCart($userId, $productId, 1);

        $this->send('PATCH', '/api/carts/'.$userId.'/items/'.$productId, ['quantity' => 5], $this->adminToken());
        self::assertResponseIsSuccessful();
        self::assertSame(5, $this->json()['items'][0]['quantity']);

        $this->send('PATCH', '/api/carts/'.$userId.'/items/'.$productId, ['quantity' => 0], $this->adminToken());
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['items']);
    }

    public function testUpdateMissingItemIs404(): void
    {
        $this->send('PATCH', '/api/carts/'.Uuid::v4().'/items/'.Uuid::v4(), ['quantity' => 2], $this->adminToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testRemoveItem(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $this->seedCart($userId, $productId, 1);

        $this->send('DELETE', '/api/carts/'.$userId.'/items/'.$productId, token: $this->adminToken());
        self::assertResponseStatusCodeSame(204);

        $this->send('GET', '/api/carts/'.$userId, token: $this->serviceToken());
        self::assertSame([], $this->json()['items']);
    }

    public function testClearCart(): void
    {
        $userId = Uuid::v4();
        $this->seedCart($userId, Uuid::v4(), 1);

        $this->send('DELETE', '/api/carts/'.$userId, token: $this->adminToken());
        self::assertResponseStatusCodeSame(204);

        $this->send('GET', '/api/carts/'.$userId, token: $this->serviceToken());
        self::assertSame([], $this->json()['items']);
    }
}
