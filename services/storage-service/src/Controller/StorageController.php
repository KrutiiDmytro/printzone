<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PresignService;
use App\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * S2S object-storage API. Firewall (security.yaml) gates writes behind
 * ROLE_STORAGE_ADMIN and reads behind any valid service token; controllers
 * only translate HTTP ⇄ FileStorageInterface / PresignService.
 */
final class StorageController
{
    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly PresignService $presign,
    ) {
    }

    #[Route('/api/storage/presign', name: 'storage_presign', methods: ['POST'])]
    public function presignPut(Request $request): JsonResponse
    {
        if (!$this->presign->supportsPresign()) {
            return new JsonResponse(['error' => 'Presigned upload is only available when STORAGE_TYPE=s3.'], 400);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $filename = isset($data['filename']) ? (string) $data['filename'] : '';
        $contentType = isset($data['contentType']) ? (string) $data['contentType'] : '';
        if ('' === $filename || '' === $contentType) {
            return new JsonResponse(['error' => 'Fields "filename" and "contentType" are required.'], 400);
        }

        try {
            return new JsonResponse($this->presign->createPresignedPut($filename, $contentType));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/storage/presign-get', name: 'storage_presign_get', methods: ['GET'])]
    public function presignGet(Request $request): JsonResponse
    {
        try {
            return new JsonResponse(['url' => $this->presign->createPresignedGet((string) $request->query->get('key', ''))]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/storage/objects', name: 'storage_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $keys = $this->storage->listKeys((string) $request->query->get('prefix', ''), $request->query->getBoolean('deep'));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse(['keys' => $keys]);
    }

    #[Route('/api/storage/objects/{key<.+>}', name: 'storage_read', methods: ['GET'])]
    public function read(Request $request, string $key): Response
    {
        try {
            $exists = $this->storage->exists($key);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        if (!$exists) {
            throw new NotFoundHttpException();
        }

        $headers = ['Content-Type' => $this->guessMimeType($key)];
        // HEAD (exists probe) must not pay for reading the whole object.
        if ($request->isMethod('HEAD')) {
            return new Response('', 200, $headers);
        }

        return new Response($this->storage->read($key), 200, $headers);
    }

    #[Route('/api/storage/objects/{key<.+>}', name: 'storage_write', methods: ['PUT'])]
    public function write(Request $request, string $key): Response
    {
        try {
            $this->storage->write($key, $request->getContent(), $request->headers->get('Content-Type'));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new Response('', 204);
    }

    #[Route('/api/storage/objects/{key<.+>}', name: 'storage_delete', methods: ['DELETE'])]
    public function delete(string $key): Response
    {
        try {
            $this->storage->delete($key);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new Response('', 204);
    }

    private function guessMimeType(string $key): string
    {
        return match (strtolower(pathinfo($key, \PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };
    }
}
