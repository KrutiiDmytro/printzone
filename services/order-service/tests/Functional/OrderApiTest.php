<?php

namespace App\Tests\Functional;

class OrderApiTest extends ApiTestCase
{
    public function testListRequiresAuth(): void
    {
        $this->send('GET', '/api/orders');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListReturnsOrders(): void
    {
        $this->seedOrder('PAID');
        $this->seedOrder('PENDING');

        $this->send('GET', '/api/orders', [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['total']);
    }

    public function testListFiltersByStatus(): void
    {
        $this->seedOrder('PAID');
        $this->seedOrder('PENDING');

        $this->send('GET', '/api/orders?status=PAID', [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        $data = $this->json()['data'];
        self::assertCount(1, $data);
        self::assertSame('PAID', $data[0]['status']);
    }

    public function testGetByIdReturnsOrderWithItems(): void
    {
        $order = $this->seedOrder('PAID');

        $this->send('GET', '/api/orders/'.$order->getId(), [], $this->serviceToken());

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame('PAID', $body['status']);
        self::assertCount(1, $body['items']);
        self::assertSame('Canon PG-540 Black', $body['items'][0]['productName']);
    }

    public function testGetUnknownIdReturns404(): void
    {
        $this->send('GET', '/api/orders/'.\Symfony\Component\Uid\Uuid::v4(), [], $this->serviceToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateStatusRequiresAdminToken(): void
    {
        $order = $this->seedOrder('PAID');

        $this->send('PUT', '/api/orders/'.$order->getId().'/status', ['status' => 'SHIPPED'], $this->serviceToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $order = $this->seedOrder('PAID');

        $this->send('PUT', '/api/orders/'.$order->getId().'/status', ['status' => 'SHIPPED'], $this->adminToken());

        self::assertResponseIsSuccessful();
        self::assertSame('SHIPPED', $this->json()['status']);
    }

    public function testUpdateStatusRejectsUnknownValue(): void
    {
        $order = $this->seedOrder('PAID');

        $this->send('PUT', '/api/orders/'.$order->getId().'/status', ['status' => 'BOGUS'], $this->adminToken());

        self::assertResponseStatusCodeSame(400);
    }
}
