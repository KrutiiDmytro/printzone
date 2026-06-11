<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\FileStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    #[Route('/media', name: 'app_media', methods: ['GET'])]
    public function __invoke(Request $request, FileStorageInterface $storage): Response
    {
        $key = (string) $request->query->get('key', '');
        if ('' === $key || !str_starts_with($key, 'products/')) {
            throw new NotFoundHttpException();
        }

        if (!$storage->exists($key)) {
            throw new NotFoundHttpException();
        }

        $body = $storage->read($key);
        $mime = $this->guessMimeType($key);

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function guessMimeType(string $key): string
    {
        $ext = strtolower(pathinfo($key, \PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
