<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\Client\StorageClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StorageClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private function client(callable $responder): StorageClient
    {
        $this->requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($responder): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $responder($method, $url, $options);
        });

        $jwt = $this->createMock(JWTTokenManagerInterface::class);
        $jwt->method('create')->willReturn('signed-token');

        return new StorageClient($http, $jwt, new NullLogger(), 'http://storage-service');
    }

    public function testWriteSendsPutWithBodyAndContentType(): void
    {
        $client = $this->client(fn () => new MockResponse('', ['http_code' => 204]));

        $client->write('exports/a/b.csv', 'id,name', 'text/csv');

        self::assertSame('PUT', $this->requests[0]['method']);
        self::assertSame('http://storage-service/api/storage/objects/exports/a/b.csv', $this->requests[0]['url']);
        self::assertSame('id,name', $this->requests[0]['options']['body']);
        self::assertContains('Content-Type: text/csv', $this->requests[0]['options']['headers']);
    }

    public function testReadReturnsBody(): void
    {
        $client = $this->client(fn () => new MockResponse('file-bytes', ['http_code' => 200]));

        self::assertSame('file-bytes', $client->read('products/x.png'));
        self::assertSame('GET', $this->requests[0]['method']);
    }

    public function testExistsIsTrueOn2xxAndFalseOn404(): void
    {
        $present = $this->client(fn () => new MockResponse('', ['http_code' => 200]));
        self::assertTrue($present->exists('products/here.png'));

        $absent = $this->client(fn () => new MockResponse('', ['http_code' => 404]));
        self::assertFalse($absent->exists('products/gone.png'));
    }

    public function testListKeysReturnsKeysArray(): void
    {
        $client = $this->client(fn () => new MockResponse(json_encode(['keys' => ['exports/a.json', 'exports/b.json']])));

        self::assertSame(['exports/a.json', 'exports/b.json'], $client->listKeys('exports', true));
        self::assertStringContainsString('/api/storage/objects', $this->requests[0]['url']);
    }

    public function testPublicUrlReturnsPresignedUrlOrNull(): void
    {
        $signed = $this->client(fn () => new MockResponse(json_encode(['url' => 'https://s3/presigned'])));
        self::assertSame('https://s3/presigned', $signed->publicUrl('products/x.png'));

        $local = $this->client(fn () => new MockResponse(json_encode(['url' => null])));
        self::assertNull($local->publicUrl('products/x.png'));
    }

    public function testKeySegmentsAreUrlEncodedButSlashesPreserved(): void
    {
        $client = $this->client(fn () => new MockResponse('x'));

        $client->read('products/uuid a.png');

        self::assertSame('http://storage-service/api/storage/objects/products/uuid%20a.png', $this->requests[0]['url']);
    }

    public function testServiceTokenIsSignedOncePerInstance(): void
    {
        $http = new MockHttpClient(fn () => new MockResponse('x'));
        $jwt = $this->createMock(JWTTokenManagerInterface::class);
        $jwt->expects($this->once())->method('create')->willReturn('signed-token');

        $client = new StorageClient($http, $jwt, new NullLogger(), 'http://storage-service');
        $client->read('products/a.png');
        $client->read('products/b.png');
    }

    public function testServerErrorThrows(): void
    {
        $client = $this->client(fn () => new MockResponse('', ['http_code' => 500]));

        $this->expectException(\RuntimeException::class);
        $client->read('products/a.png');
    }
}
