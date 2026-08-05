<?php

namespace App\Tests\Functional;

class HealthTest extends ApiTestCase
{
    public function testLiveReturnsOk(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json()['status']);
    }

    public function testReadyChecksDatabase(): void
    {
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertSame('ok', $data['status']);
        self::assertSame('ok', $data['checks']['database']['status']);
    }
}
