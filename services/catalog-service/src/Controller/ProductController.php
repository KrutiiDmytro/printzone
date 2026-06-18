<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products')]
class ProductController
{
    private const MAX_LIMIT = 500;

    public function __construct(private readonly ProductRepository $products)
    {
    }

    #[Route('', name: 'products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Cart path: fetch a specific set of products by id (snapshot refresh).
        $ids = array_filter(explode(',', (string) $request->query->get('ids', '')));
        if ([] !== $ids) {
            return new JsonResponse(['data' => array_map($this->serialize(...), $this->products->findByIds($ids))]);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', 50)));

        $filters = [
            'category' => $request->query->get('category'),
            'categorySlug' => $request->query->get('categorySlug'),
            'brandSlug' => $request->query->get('brandSlug'),
            'q' => $request->query->get('q'),
            'sort' => $request->query->get('sort', ''),
            'isFeatured' => $request->query->get('isFeatured', ''),
            'availableOnly' => $request->query->getBoolean('availableOnly'),
            'priceMin' => $request->query->get('priceMin'),
            'priceMax' => $request->query->get('priceMax'),
            'stockMin' => $request->query->get('stockMin'),
            'stockMax' => $request->query->get('stockMax'),
        ];

        return new JsonResponse([
            'data' => array_map($this->serialize(...), $this->products->findByFilters($filters, $page, $limit)),
            'page' => $page,
            'limit' => $limit,
            'total' => $this->products->countByFilters($filters),
            'priceRange' => $this->products->priceRange($filters),
        ]);
    }

    #[Route('/{id}', name: 'products_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $product = $this->products->findOneBy(['id' => $id]);
        if (null === $product) {
            throw new NotFoundHttpException('Product not found.');
        }

        return new JsonResponse($this->serialize($product));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $product): array
    {
        $category = $product->getCategory();
        $brand = $product->getBrand();

        return [
            'id' => (string) $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'isFeatured' => $product->isFeatured(),
            'image' => $product->getImage(),
            'category' => null === $category ? null : [
                'id' => (string) $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ],
            'brand' => null === $brand ? null : [
                'id' => (string) $brand->getId(),
                'name' => $brand->getName(),
                'slug' => $brand->getSlug(),
                'color' => $brand->getColor(),
            ],
        ];
    }
}
