<?php

namespace App\Controller;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    public function __construct(
        private readonly S3Client $s3Client,
        #[Autowire('%aws.s3.bucket%')]
        private readonly string $bucket,
        #[Autowire('%env(STORAGE_TYPE)%')]
        private readonly string $storageType,
        #[Autowire('%local_storage_root%')]
        private readonly string $localRoot,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        // The service is stateless (no DB); readiness = the configured object
        // backend is reachable. S3 → HeadBucket; local → the storage root is a
        // writable directory. The broker is not a dependency here (pure sync HTTP).
        [$status, $detail] = 's3' === strtolower(trim($this->storageType))
            ? $this->checkS3()
            : $this->checkLocal();

        if ('ok' !== $status) {
            return new JsonResponse([
                'status' => 'error',
                'checks' => ['storage' => ['status' => 'error', 'detail' => $detail]],
            ], 503);
        }

        return new JsonResponse(['status' => 'ok', 'checks' => ['storage' => ['status' => 'ok']]]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkS3(): array
    {
        if ('' === trim($this->bucket)) {
            return ['error', 'AWS_S3_BUCKET is not configured'];
        }

        try {
            $this->s3Client->headBucket(['Bucket' => $this->bucket]);
        } catch (\Throwable $e) {
            return ['error', $e->getMessage()];
        }

        return ['ok', ''];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function checkLocal(): array
    {
        if (!is_dir($this->localRoot) && !@mkdir($this->localRoot, 0o755, true) && !is_dir($this->localRoot)) {
            return ['error', sprintf('%s is not a directory', $this->localRoot)];
        }

        if (!is_writable($this->localRoot)) {
            return ['error', sprintf('%s is not writable', $this->localRoot)];
        }

        return ['ok', ''];
    }
}
