<?php

namespace App\Controller;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

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

    #[Route('', name: 'brands_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $brand = new Brand();
        if (null !== $error = $this->hydrate($brand, $request, true)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->brands->save($brand, true);

        return new JsonResponse($this->serialize($brand), 201);
    }

    #[Route('/{id}', name: 'brands_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $brand = $this->find($id);
        if (null !== $error = $this->hydrate($brand, $request, false)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->brands->save($brand, true);

        return new JsonResponse($this->serialize($brand));
    }

    #[Route('/{id}', name: 'brands_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->brands->remove($this->find($id), true);

        return new JsonResponse(null, 204);
    }

    private function find(string $id): Brand
    {
        $brand = Uuid::isValid($id) ? $this->brands->findOneBy(['id' => $id]) : null;
        if (null === $brand) {
            throw new NotFoundHttpException('Brand not found.');
        }

        return $brand;
    }

    private function hydrate(Brand $brand, Request $request, bool $isCreate): ?string
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            return 'Invalid JSON body.';
        }

        if ($isCreate || \array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ('' === $name) {
                return 'Field "name" is required.';
            }
            $brand->setName($name);
        }

        if ($isCreate || \array_key_exists('slug', $data)) {
            $slug = trim((string) ($data['slug'] ?? ''));
            if ('' === $slug) {
                return 'Field "slug" is required.';
            }
            $existing = $this->brands->findBySlug($slug);
            if (null !== $existing && !$existing->getId()->equals($brand->getId())) {
                return 'Slug already in use.';
            }
            $brand->setSlug($slug);
        }

        if (\array_key_exists('color', $data)) {
            $color = $data['color'];
            if (null === $color || '' === $color) {
                $brand->setColor(null);
            } elseif (!is_string($color) || 1 !== preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                return 'Field "color" must be a hex color like "#cc0000" or null.';
            } else {
                $brand->setColor($color);
            }
        }

        return null;
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
