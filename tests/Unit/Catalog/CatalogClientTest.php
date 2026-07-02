<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Client\CatalogClient;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CatalogClientTest extends TestCase
{
    public function testRepeatedReadsHitTheServiceOnlyOnce(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['data' => [
                ['id' => 'c1', 'name' => 'Cat', 'slug' => 'cat', 'parentId' => null],
            ]]));
        });

        $jwt = $this->createMock(JWTTokenManagerInterface::class);
        // The service JWT is signed at most once per request, not per call.
        $jwt->expects($this->once())->method('create')->willReturn('signed-token');

        $client = new CatalogClient($http, $jwt, new NullLogger(), 'http://catalog-service');

        $client->rootCategories();
        $client->rootCategories();
        $client->rootCategories();

        // 18 navbar/home renders collapse into a single round-trip.
        $this->assertSame(1, $calls);
    }

    public function testDistinctPathsAreCachedSeparately(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(json_encode(['data' => []]));
        });

        $jwt = $this->createMock(JWTTokenManagerInterface::class);
        $jwt->method('create')->willReturn('signed-token');

        $client = new CatalogClient($http, $jwt, new NullLogger(), 'http://catalog-service');

        $client->rootCategories(); // /api/categories
        $client->brands();         // /api/brands
        $client->rootCategories(); // cached
        $client->brands();         // cached

        $this->assertSame(2, $calls);
    }
}
