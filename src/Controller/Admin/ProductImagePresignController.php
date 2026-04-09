<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ProductImagePresignService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProductImagePresignController extends AbstractController
{
    public function __construct(
        private readonly ProductImagePresignService $presignService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/admin/product-image/presign', name: 'admin_product_image_presign', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Request $request): JsonResponse
    {
        $csrfHeader = $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('submit', (string) $csrfHeader))) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->presignService->supportsPresign()) {
            return new JsonResponse(
                ['error' => 'Presigned upload is only available when STORAGE_TYPE=s3.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $filename = isset($data['filename']) ? (string) $data['filename'] : '';
        $contentType = isset($data['contentType']) ? (string) $data['contentType'] : '';

        if ('' === $filename || '' === $contentType) {
            return new JsonResponse(
                ['error' => 'Fields "filename" and "contentType" are required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $payload = $this->presignService->createPresignedPut($filename, $contentType);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => 'Could not create presigned URL.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse($payload);
    }
}
