<?php

namespace App\Controller;

use App\Repository\BrandRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        [$filters, $priceParams] = $this->buildFilters($request);
        $products   = $productRepository->findWithFilters($filters);
        $priceRange = $productRepository->getPriceRange();
        $categories = $categoryRepository->findAllRootCategories();

        return $this->render('shop/index.html.twig', [
            'products'    => $products,
            'categories'  => $categories,
            'priceRange'  => $priceRange,
            'priceParams' => $priceParams,
            'currentSort' => $filters['sort'] ?? '',
        ]);
    }

    #[Route('/category/{slug}', name: 'app_category_show')]
    public function showCategory(
        string $slug,
        Request $request,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        $category = $categoryRepository->findBySlug($slug);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        [$filters, $priceParams] = $this->buildFilters($request, category: $category);
        $products   = $productRepository->findWithFilters($filters);
        $priceRange = $productRepository->getPriceRange();
        $categories = $categoryRepository->findAllRootCategories();

        return $this->render('shop/index.html.twig', [
            'products'       => $products,
            'categories'     => $categories,
            'currentCategory'=> $category,
            'priceRange'     => $priceRange,
            'priceParams'    => $priceParams,
            'currentSort'    => $filters['sort'] ?? '',
        ]);
    }

    #[Route('/brand/{slug}', name: 'shop_brand')]
    public function showBrand(
        string $slug,
        Request $request,
        BrandRepository $brandRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $brand = $brandRepository->findBySlug($slug);

        if (!$brand) {
            throw $this->createNotFoundException('Brand not found');
        }

        [$filters, $priceParams] = $this->buildFilters($request, brand: $brand);
        $products   = $productRepository->findWithFilters($filters);
        $priceRange = $productRepository->getPriceRange();

        return $this->render('shop/index.html.twig', [
            'products'    => $products,
            'categories'  => $categoryRepository->findAllRootCategories(),
            'currentBrand'=> $brand,
            'priceRange'  => $priceRange,
            'priceParams' => $priceParams,
            'currentSort' => $filters['sort'] ?? '',
        ]);
    }

    #[Route('/brand/{brandSlug}/category/{categorySlug}', name: 'shop_brand_category')]
    public function showBrandCategory(
        string $brandSlug,
        string $categorySlug,
        Request $request,
        BrandRepository $brandRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        $brand    = $brandRepository->findBySlug($brandSlug);
        $category = $categoryRepository->findBySlug($categorySlug);

        if (!$brand || !$category) {
            throw $this->createNotFoundException();
        }

        [$filters, $priceParams] = $this->buildFilters($request, category: $category, brand: $brand);
        $products   = $productRepository->findWithFilters($filters);
        $priceRange = $productRepository->getPriceRange();

        return $this->render('shop/index.html.twig', [
            'products'        => $products,
            'categories'      => $categoryRepository->findAllRootCategories(),
            'currentBrand'    => $brand,
            'currentCategory' => $category,
            'priceRange'      => $priceRange,
            'priceParams'     => $priceParams,
            'currentSort'     => $filters['sort'] ?? '',
        ]);
    }

    #[Route('/categories', name: 'app_categories')]
    public function categories(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAllRootCategories();

        return $this->render('shop/categories.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * Extracts price/sort params from request and builds the filters array.
     *
     * @return array{0: array, 1: array{min: float|null, max: float|null}}
     */
    private function buildFilters(
        Request $request,
        mixed $category = null,
        mixed $brand = null
    ): array {
        $priceMinEur = $request->query->get('price_min');
        $priceMaxEur = $request->query->get('price_max');
        $sort        = $request->query->get('sort', '');

        $filters = [
            'category' => $category,
            'brand'    => $brand,
            'sort'     => $sort,
        ];

        $priceParams = ['min' => null, 'max' => null];

        if ($priceMinEur !== null && $priceMinEur !== '') {
            $filters['minPrice']    = (int) round((float) $priceMinEur * 100);
            $priceParams['min']     = (float) $priceMinEur;
        }
        if ($priceMaxEur !== null && $priceMaxEur !== '') {
            $filters['maxPrice']    = (int) round((float) $priceMaxEur * 100);
            $priceParams['max']     = (float) $priceMaxEur;
        }

        return [$filters, $priceParams];
    }
}
