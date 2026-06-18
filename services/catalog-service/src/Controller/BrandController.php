<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/brands')]
class BrandController
{
    public function __construct(private readonly BrandRepository $brands)
    {
    }

    #[Route('', name: 'brands_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['data' => array_map($this->serialize(...), $this->brands->findAllOrdered())]);
    }

    #[Route('/{slug}', name: 'brands_get', methods: ['GET'])]
    public function get(string $slug): JsonResponse
    {
        $brand = $this->brands->findBySlug($slug);
        if (null === $brand) {
            throw new NotFoundHttpException('Brand not found.');
        }

        return new JsonResponse($this->serialize($brand));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Brand $brand): array
    {
        return [
            'id' => (string) $brand->getId(),
            'name' => $brand->getName(),
            'slug' => $brand->getSlug(),
            'color' => $brand->getColor(),
        ];
    }
}
