<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/categories')]
class CategoryController
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    #[Route('', name: 'categories_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $items = $request->query->getBoolean('root')
            ? $this->categories->findAllRoot()
            : $this->categories->findBy([], ['name' => 'ASC']);

        return new JsonResponse(['data' => array_map($this->serialize(...), $items)]);
    }

    #[Route('/{slug}', name: 'categories_get', methods: ['GET'])]
    public function get(string $slug): JsonResponse
    {
        $category = $this->categories->findBySlug($slug);
        if (null === $category) {
            throw new NotFoundHttpException('Category not found.');
        }

        return new JsonResponse($this->serialize($category));
    }

    #[Route('', name: 'categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $category = new Category();
        if (null !== $error = $this->hydrate($category, $request, true)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->categories->save($category, true);

        return new JsonResponse($this->serialize($category), 201);
    }

    #[Route('/{id}', name: 'categories_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $category = $this->find($id);
        if (null !== $error = $this->hydrate($category, $request, false)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->categories->save($category, true);

        return new JsonResponse($this->serialize($category));
    }

    #[Route('/{id}', name: 'categories_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->categories->remove($this->find($id), true);

        return new JsonResponse(null, 204);
    }

    private function find(string $id): Category
    {
        $category = Uuid::isValid($id) ? $this->categories->findOneBy(['id' => $id]) : null;
        if (null === $category) {
            throw new NotFoundHttpException('Category not found.');
        }

        return $category;
    }

    private function hydrate(Category $category, Request $request, bool $isCreate): ?string
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
            $category->setName($name);
        }

        if ($isCreate || \array_key_exists('slug', $data)) {
            $slug = trim((string) ($data['slug'] ?? ''));
            if ('' === $slug) {
                return 'Field "slug" is required.';
            }
            $existing = $this->categories->findBySlug($slug);
            if (null !== $existing && !$existing->getId()->equals($category->getId())) {
                return 'Slug already in use.';
            }
            $category->setSlug($slug);
        }

        if (\array_key_exists('parent', $data)) {
            $parentId = $data['parent'];
            if (null === $parentId || '' === $parentId) {
                $category->setParent(null);
            } elseif (!Uuid::isValid((string) $parentId)) {
                return 'Field "parent" must be a valid UUID or null.';
            } elseif (null === $parent = $this->categories->findOneBy(['id' => (string) $parentId])) {
                return 'Parent category not found.';
            } elseif ($parent->getId()->equals($category->getId())) {
                return 'A category cannot be its own parent.';
            } else {
                $category->setParent($parent);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Category $category): array
    {
        return [
            'id' => (string) $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'parentId' => $category->getParent() ? (string) $category->getParent()->getId() : null,
        ];
    }
}
