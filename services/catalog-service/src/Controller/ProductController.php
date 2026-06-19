<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/products')]
class ProductController
{
    private const MAX_LIMIT = 500;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly BrandRepository $brands,
    ) {
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
        return new JsonResponse($this->serialize($this->find($id)));
    }

    #[Route('', name: 'products_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $product = new Product();
        if (null !== $error = $this->hydrate($product, $request, true)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->products->save($product, true);

        return new JsonResponse($this->serialize($product), 201);
    }

    #[Route('/{id}', name: 'products_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $product = $this->find($id);
        if (null !== $error = $this->hydrate($product, $request, false)) {
            return new JsonResponse(['error' => $error], 422);
        }
        $this->products->save($product, true);

        return new JsonResponse($this->serialize($product));
    }

    #[Route('/{id}', name: 'products_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->products->remove($this->find($id), true);

        return new JsonResponse(null, 204);
    }

    private function find(string $id): Product
    {
        $product = Uuid::isValid($id) ? $this->products->findOneBy(['id' => $id]) : null;
        if (null === $product) {
            throw new NotFoundHttpException('Product not found.');
        }

        return $product;
    }

    /**
     * Applies the JSON body to the product. On create all required fields must be
     * present; on update only the provided fields change. Returns an error string
     * (→ 422) or null on success.
     */
    private function hydrate(Product $product, Request $request, bool $isCreate): ?string
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
            $product->setName($name);
        }

        if ($isCreate || \array_key_exists('price', $data)) {
            if (!isset($data['price']) || !is_numeric($data['price']) || (int) $data['price'] < 0) {
                return 'Field "price" must be a non-negative integer (cents).';
            }
            $product->setPrice((int) $data['price']);
        }

        if ($isCreate || \array_key_exists('stock', $data)) {
            if (!isset($data['stock']) || !is_numeric($data['stock']) || (int) $data['stock'] < 0) {
                return 'Field "stock" must be a non-negative integer.';
            }
            $product->setStock((int) $data['stock']);
        }

        if ($isCreate || \array_key_exists('category', $data)) {
            $categoryId = (string) ($data['category'] ?? '');
            if ('' === $categoryId || !Uuid::isValid($categoryId)) {
                return 'Field "category" must be a valid UUID.';
            }
            $category = $this->categories->findOneBy(['id' => $categoryId]);
            if (null === $category) {
                return 'Category not found.';
            }
            $product->setCategory($category);
        }

        if (\array_key_exists('brand', $data)) {
            $brandId = $data['brand'];
            if (null === $brandId || '' === $brandId) {
                $product->setBrand(null);
            } elseif (!Uuid::isValid((string) $brandId)) {
                return 'Field "brand" must be a valid UUID or null.';
            } elseif (null === $brand = $this->brands->findOneBy(['id' => (string) $brandId])) {
                return 'Brand not found.';
            } else {
                $product->setBrand($brand);
            }
        }

        if (\array_key_exists('description', $data)) {
            $product->setDescription(null !== $data['description'] ? (string) $data['description'] : null);
        }

        if (\array_key_exists('image', $data)) {
            $product->setImage(null !== $data['image'] && '' !== $data['image'] ? (string) $data['image'] : null);
        }

        if (\array_key_exists('isFeatured', $data)) {
            $product->setIsFeatured((bool) $data['isFeatured']);
        }

        return null;
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
