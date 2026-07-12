<?php

namespace App\Tests\Functional;

/**
 * Exercises the S2S object API against the local disk backend (.env.test →
 * STORAGE_TYPE=local). S3-only paths (presign) assert the local guard.
 */
class StorageApiTest extends ApiTestCase
{
    public function testWriteReadHeadDeleteRoundTrip(): void
    {
        $key = 'exports/products/csv/roundtrip-'.bin2hex(random_bytes(4)).'.csv';
        $body = "id,name\n1,Canon\n";

        $this->put('/api/storage/objects/'.$key, $body, 'text/csv', $this->adminToken());
        self::assertResponseStatusCodeSame(204);

        $this->send('GET', '/api/storage/objects/'.$key, $this->serviceToken());
        self::assertResponseIsSuccessful();
        self::assertSame($body, $this->client->getResponse()->getContent());
        self::assertStringStartsWith('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $this->send('HEAD', '/api/storage/objects/'.$key, $this->serviceToken());
        self::assertResponseIsSuccessful();
        self::assertEmpty($this->client->getResponse()->getContent());

        $this->send('DELETE', '/api/storage/objects/'.$key, $this->adminToken());
        self::assertResponseStatusCodeSame(204);

        $this->send('GET', '/api/storage/objects/'.$key, $this->serviceToken());
        self::assertResponseStatusCodeSame(404);
    }

    public function testListReturnsWrittenKeys(): void
    {
        $prefix = 'exports/list-'.bin2hex(random_bytes(4));
        $this->put('/api/storage/objects/'.$prefix.'/a.json', '{}', 'application/json', $this->adminToken());
        $this->put('/api/storage/objects/'.$prefix.'/b.json', '{}', 'application/json', $this->adminToken());

        $this->send('GET', '/api/storage/objects?prefix='.$prefix.'&deep=1', $this->serviceToken());
        self::assertResponseIsSuccessful();

        $keys = $this->json()['keys'];
        self::assertContains($prefix.'/a.json', $keys);
        self::assertContains($prefix.'/b.json', $keys);
    }

    public function testReadMissingIs404(): void
    {
        $this->send('GET', '/api/storage/objects/exports/nope/missing.csv', $this->serviceToken());
        self::assertResponseStatusCodeSame(404);
    }

    public function testReadWithoutTokenIs401(): void
    {
        $this->send('GET', '/api/storage/objects/exports/whatever.csv');
        self::assertResponseStatusCodeSame(401);
    }

    public function testWriteWithoutAdminRoleIs403(): void
    {
        $this->put('/api/storage/objects/exports/forbidden.csv', 'x', 'text/csv', $this->serviceToken());
        self::assertResponseStatusCodeSame(403);
    }

    public function testPresignPutInLocalModeIs400(): void
    {
        $this->send('POST', '/api/storage/presign', $this->adminToken(), ['filename' => 'a.png', 'contentType' => 'image/png']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testPresignGetInLocalModeReturnsNull(): void
    {
        $this->send('GET', '/api/storage/presign-get?key=products/x.png', $this->serviceToken());
        self::assertResponseIsSuccessful();
        self::assertNull($this->json()['url']);
    }

    public function testListWithTraversalPrefixIs400(): void
    {
        $this->send('GET', '/api/storage/objects?prefix='.rawurlencode('../secret'), $this->serviceToken());
        self::assertResponseStatusCodeSame(400);
    }
}
