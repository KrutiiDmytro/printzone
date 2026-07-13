<?php

namespace App\Tests\Unit;

use App\Service\PresignService;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

class PresignServiceTest extends TestCase
{
    private function s3(): S3Client
    {
        // Dummy client — presigning signs locally, so no network/credentials needed.
        return new S3Client([
            'version' => 'latest',
            'region' => 'eu-north-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
    }

    private function service(string $storageType): PresignService
    {
        return new PresignService($this->s3(), 'printzone-test-bucket', $storageType);
    }

    public function testPresignedPutBuildsProductsKeyAndSignedUrl(): void
    {
        $out = $this->service('s3')->createPresignedPut('My Photo!.PNG', 'image/png');

        self::assertSame('PUT', $out['method']);
        self::assertSame('image/png', $out['headers']['Content-Type']);
        self::assertStringStartsWith('products/', $out['key']);
        self::assertStringEndsWith('-My_Photo_.PNG', $out['key']);
        self::assertStringContainsString('printzone-test-bucket', $out['url']);
        self::assertStringContainsString('X-Amz-Signature', $out['url']);
    }

    public function testPresignedPutRejectsDisallowedMime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service('s3')->createPresignedPut('doc.pdf', 'application/pdf');
    }

    public function testPresignUnavailableInLocalMode(): void
    {
        $svc = $this->service('local');
        self::assertFalse($svc->supportsPresign());
        self::assertNull($svc->createPresignedGet('products/x.png'));

        $this->expectException(\RuntimeException::class);
        $svc->createPresignedPut('a.png', 'image/png');
    }

    public function testPresignedGetSignsKeyInS3Mode(): void
    {
        $url = $this->service('s3')->createPresignedGet('products/abc.png');

        self::assertNotNull($url);
        self::assertStringContainsString('products/abc.png', $url);
        self::assertStringContainsString('X-Amz-Signature', $url);
    }

    public function testPresignedGetRejectsTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service('s3')->createPresignedGet('../secret.png');
    }
}
