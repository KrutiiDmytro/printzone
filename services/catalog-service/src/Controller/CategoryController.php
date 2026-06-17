<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

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
