<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\FileStorageFactory;
use App\Storage\FlysystemFileStorage;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Повний цикл через FileStorageFactory + AwsS3V3Adapter з моком HTTP (Aws\MockHandler).
 */
final class FileStorageFactoryS3Test extends TestCase
{
    public function testS3FullLifecycleWriteReadExistsListDelete(): void
    {
        $tempRoot = sys_get_temp_dir().'/s3_factory_unused_'.bin2hex(random_bytes(4));
        mkdir($tempRoot, 0o777, true);

        try {
            $mock = new MockHandler([
                new Result([]),
                new Result(['ContentLength' => 11, '@metadata' => ['statusCode' => 200]]),
                new Result([
                    'Body' => Utils::streamFor('hello-s3'),
                    '@metadata' => ['statusCode' => 200],
                ]),
                new Result([
                    'Contents' => [
                        [
                            'Key' => 'scope/file.txt',
                            'Size' => 11,
                        ],
                    ],
                    'IsTruncated' => false,
                    '@metadata' => ['statusCode' => 200],
                ]),
                new Result(['@metadata' => ['statusCode' => 200]]),
                static function (\Aws\CommandInterface $command) {
                    return new S3Exception('Not Found', $command, [
                        'response' => new Response(404), 'code' => 'NotFound',
                    ]);
                },
            ]);

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => 'eu-west-1',
                'credentials' => ['key' => 'test', 'secret' => 'test'],
                'handler' => $mock,
            ]);

            $factory = new FileStorageFactory($tempRoot, $s3, 'bucket-name', 's3');
            $storage = $factory->create();
            $this->assertInstanceOf(FlysystemFileStorage::class, $storage);

            $storage->write('scope/file.txt', 'hello-s3', 'text/plain');

            $this->assertTrue($storage->exists('scope/file.txt'));
            $this->assertSame('hello-s3', $storage->read('scope/file.txt'));

            $keys = $storage->listKeys('scope', false);
            $this->assertSame(['scope/file.txt'], $keys);

            $storage->delete('scope/file.txt');
            $this->assertFalse($storage->exists('scope/file.txt'));
        } finally {
            $this->removeDir($tempRoot);
        }
    }

    public function testS3PublicUrlDoesNotConsumeMockQueue(): void
    {
        $tempRoot = sys_get_temp_dir().'/s3_factory_url_'.bin2hex(random_bytes(4));
        mkdir($tempRoot, 0o777, true);

        try {
            $mock = new MockHandler([]);
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => 'eu-west-1',
                'credentials' => ['key' => 'test', 'secret' => 'test'],
                'handler' => $mock,
            ]);

            $factory = new FileStorageFactory($tempRoot, $s3, 'bucket-name', 's3');
            $storage = $factory->create();

            $url = $storage->publicUrl('scope/file.txt');
            $this->assertStringContainsString('bucket-name', (string) $url);
            $this->assertStringContainsString('scope', (string) $url);
            $this->assertStringContainsString('file.txt', (string) $url);
            $this->assertSame(0, count($mock));
        } finally {
            $this->removeDir($tempRoot);
        }
    }

    public function testS3HeadObjectMissingReturnsFalseForExists(): void
    {
        $tempRoot = sys_get_temp_dir().'/s3_factory_miss_'.bin2hex(random_bytes(4));
        mkdir($tempRoot, 0o777, true);

        try {
            $mock = new MockHandler([
                static function (\Aws\CommandInterface $command) {
                    return new S3Exception('Not Found', $command, [
                        'response' => new Response(404), 'code' => 'NotFound',
                    ]);
                },
            ]);

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => 'eu-west-1',
                'credentials' => ['key' => 'test', 'secret' => 'test'],
                'handler' => $mock,
            ]);

            $factory = new FileStorageFactory($tempRoot, $s3, 'bucket-name', 's3');
            $storage = $factory->create();

            $this->assertFalse($storage->exists('missing/key.txt'));
        } finally {
            $this->removeDir($tempRoot);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
