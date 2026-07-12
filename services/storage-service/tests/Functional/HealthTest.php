<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthTest extends WebTestCase
{
    private ?string $originalStorageType;
    private ?string $originalBucket;

    protected function setUp(): void
    {
        $this->originalStorageType = $_ENV['STORAGE_TYPE'] ?? null;
        $this->originalBucket = $_ENV['AWS_S3_BUCKET'] ?? null;
    }

    protected function tearDown(): void
    {
        // Restore env this test mutates so an s3/empty-bucket combo can't leak
        // into later tests booting a kernel with different storage config.
        $this->restore('STORAGE_TYPE', $this->originalStorageType);
        $this->restore('AWS_S3_BUCKET', $this->originalBucket);
        parent::tearDown();
    }

    public function testLiveIsPublicAndOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json($client)['status']);
    }

    public function testReadyReportsStorageOkWithLocalBackend(): void
    {
        // .env.test uses STORAGE_TYPE=local → checks var/storage is writable → ok.
        $client = static::createClient();
        $client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json($client)['checks']['storage']['status']);
    }

    public function testReadyReturns503WhenS3BucketMissing(): void
    {
        // STORAGE_TYPE=s3 with no bucket configured is a hermetic misconfig path:
        // the empty-bucket guard short-circuits before any network call.
        $_SERVER['STORAGE_TYPE'] = $_ENV['STORAGE_TYPE'] = 's3';
        $_SERVER['AWS_S3_BUCKET'] = $_ENV['AWS_S3_BUCKET'] = '';

        $client = static::createClient();
        $client->request('GET', '/health/ready');

        self::assertResponseStatusCodeSame(503);
        self::assertSame('error', $this->json($client)['status']);
    }

    public function testUnknownRouteIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/no-such-route');

        self::assertResponseStatusCodeSame(404);
    }

    private function restore(string $key, ?string $value): void
    {
        if (null === $value) {
            unset($_SERVER[$key], $_ENV[$key]);
        } else {
            $_SERVER[$key] = $_ENV[$key] = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}
