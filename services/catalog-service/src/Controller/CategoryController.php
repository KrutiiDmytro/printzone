<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
class CategoryController
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    #[Route('', name: 'categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map($this->serialize(...), $this->categories->findBy([], ['name' => 'ASC']));

        return new JsonResponse(['data' => $data]);
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
