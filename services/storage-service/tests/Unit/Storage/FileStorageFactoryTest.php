<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\FileStorageFactory;
use App\Storage\FlysystemFileStorage;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

final class FileStorageFactoryTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir().'/file_storage_factory_test_'.bin2hex(random_bytes(8));
        mkdir($this->tempRoot, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    public function testCreateLocalWritesUnderLocalRoot(): void
    {
        $s3 = $this->createMock(S3Client::class);
        $factory = new FileStorageFactory($this->tempRoot, $s3, 'unused-bucket', 'local');
        $storage = $factory->create();

        $this->assertInstanceOf(FlysystemFileStorage::class, $storage);

        $storage->write('nested/key.txt', 'hello');

        $this->assertFileExists($this->tempRoot.'/nested/key.txt');
        $this->assertSame('hello', $storage->read('nested/key.txt'));
        $this->assertSame(['nested/key.txt'], $storage->listKeys('nested', false));
        $this->assertNull($storage->publicUrl('nested/key.txt'));

        $storage->delete('nested/key.txt');
        $this->assertFalse($storage->exists('nested/key.txt'));
        $this->assertSame([], $storage->listKeys('nested', false));
    }

    public function testCreateS3ReturnsStorageWithPublicUrlFromClient(): void
    {
        $s3 = $this->createMock(S3Client::class);
        $s3->method('getObjectUrl')
            ->willReturn('https://bucket.s3.amazonaws.com/obj-key');

        $factory = new FileStorageFactory($this->tempRoot, $s3, 'bucket-name', 's3');
        $storage = $factory->create();

        $this->assertInstanceOf(FlysystemFileStorage::class, $storage);
        $this->assertSame('https://bucket.s3.amazonaws.com/obj-key', $storage->publicUrl('obj-key'));
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
