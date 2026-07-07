<?php

namespace App\Tests\Functional;

class HealthTest extends ApiTestCase
{
    public function testLiveIsPublicAndOk(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json()['status']);
    }

    public function testReadyChecksDatabase(): void
    {
        $this->client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json()['checks']['database']['status']);
    }

    public function testUnknownRouteIs404(): void
    {
        $this->client->request('GET', '/no-such-route');

        self::assertResponseStatusCodeSame(404);
    }
}
