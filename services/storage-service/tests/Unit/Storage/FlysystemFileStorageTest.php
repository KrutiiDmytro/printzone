<?php

declare(strict_types=1);

namespace App\Tests\Unit\Storage;

use App\Storage\FlysystemFileStorage;
use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

final class FlysystemFileStorageTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir().'/flysystem_storage_test_'.bin2hex(random_bytes(8));
        mkdir($this->tempRoot, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    public function testWriteReadDeleteExistsWithLocalAdapter(): void
    {
        $storage = $this->makeLocalStorage();

        $storage->write('products/1-image.jpg', 'binary-data', 'image/jpeg');

        $this->assertTrue($storage->exists('products/1-image.jpg'));
        $this->assertSame('binary-data', $storage->read('products/1-image.jpg'));

        $storage->delete('products/1-image.jpg');

        $this->assertFalse($storage->exists('products/1-image.jpg'));
    }

    public function testPublicUrlReturnsNullWithoutS3(): void
    {
        $storage = $this->makeLocalStorage();

        $this->assertNull($storage->publicUrl('products/x.jpg'));
    }

    public function testPublicUrlUsesS3ClientWhenConfigured(): void
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->tempRoot));
        $s3 = $this->createMock(S3Client::class);
        $s3->expects($this->once())
            ->method('getObjectUrl')
            ->with('my-bucket', 'products/5.jpg')
            ->willReturn('https://cdn.example.com/products/5.jpg');

        $storage = new FlysystemFileStorage($filesystem, 'my-bucket', $s3);

        $this->assertSame('https://cdn.example.com/products/5.jpg', $storage->publicUrl('products/5.jpg'));
    }

    public function testReadThrowsWhenFileMissing(): void
    {
        $storage = $this->makeLocalStorage();

        $this->expectException(\RuntimeException::class);
        $storage->read('missing.txt');
    }

    public function testInvalidKeyEmptyThrows(): void
    {
        $storage = $this->makeLocalStorage();

        $this->expectException(\InvalidArgumentException::class);
        $storage->write('', 'x');
    }

    public function testInvalidKeyPathTraversalThrows(): void
    {
        $storage = $this->makeLocalStorage();

        $this->expectException(\InvalidArgumentException::class);
        $storage->read('a/../b');
    }

    public function testListKeysShallowReturnsOnlyDirectFiles(): void
    {
        $storage = $this->makeLocalStorage();
        $storage->write('root.txt', 'a');
        $storage->write('sub/a.txt', 'b');
        $storage->write('sub/b.txt', 'c');

        $rootFiles = $storage->listKeys('', false);
        $this->assertSame(['root.txt'], $rootFiles);

        $subFiles = $storage->listKeys('sub', false);
        $this->assertSame(['sub/a.txt', 'sub/b.txt'], $subFiles);
    }

    public function testListKeysDeepIncludesNestedFiles(): void
    {
        $storage = $this->makeLocalStorage();
        $storage->write('deep/nested/file.txt', 'x');

        $nested = $storage->listKeys('', true);
        $this->assertSame(['deep/nested/file.txt'], $nested);
    }

    public function testListInvalidPrefixThrows(): void
    {
        $storage = $this->makeLocalStorage();

        $this->expectException(\InvalidArgumentException::class);
        $storage->listKeys('a/../b');
    }

    private function makeLocalStorage(): FlysystemFileStorage
    {
        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->tempRoot));

        return new FlysystemFileStorage($filesystem);
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
