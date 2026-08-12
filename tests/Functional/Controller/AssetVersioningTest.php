<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\WebTestCase;

/**
 * nginx serves the static locations as immutable for 30 days, so a rendered
 * page must never point at a bare filename: the browser would keep serving the
 * previous release's file without revalidating. This asserts against a real
 * rendered page, because the version only appears once the container wires the
 * asset packages together — a unit test cannot show that.
 */
final class AssetVersioningTest extends WebTestCase
{
    public function testRenderedStaticUrlsCarryAVersion(): void
    {
        $client = static::createClient();
        $this->createSchema();

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $stylesheet = $crawler->filter('link[href*="css/style.css"]')->attr('href');

        // Absolute, so it resolves the same from /product/{id} as from the root;
        // versioned, so a deploy is not hidden behind the immutable cache.
        self::assertStringStartsWith('/css/style.css?v=', (string) $stylesheet);
        self::assertMatchesRegularExpression('#\?v=\d+$#', (string) $stylesheet);
    }
}
