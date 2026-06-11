<?php

declare(strict_types=1);

namespace App\Storage;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

final class FileStorageFactory
{
    public function __construct(
        private readonly string $localRoot,
        private readonly S3Client $s3Client,
        private readonly string $s3Bucket,
        private readonly string $storageType,
    ) {
    }

    public function create(): FileStorageInterface
    {
        if ('s3' === strtolower(trim($this->storageType))) {
            $adapter = new AwsS3V3Adapter($this->s3Client, $this->s3Bucket);
            $filesystem = new Filesystem($adapter);

            return new FlysystemFileStorage($filesystem, $this->s3Bucket, $this->s3Client);
        }

        $adapter = new LocalFilesystemAdapter(
            $this->localRoot,
            PortableVisibilityConverter::fromArray([
                'file' => ['public' => 0o644, 'private' => 0o600],
                'dir' => ['public' => 0o755, 'private' => 0o700],
            ], 'public'),
        );
        $filesystem = new Filesystem($adapter);

        return new FlysystemFileStorage($filesystem);
    }
}
