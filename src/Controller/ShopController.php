<?php

namespace App\Controller;

use App\Catalog\Client\CatalogClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShopController extends AbstractController
{
    public function __construct(private readonly CatalogClient $catalog)
    {
    }

    #[Route('/shop', name: 'app_shop')]
    public function index(Request $request): Response
    {
        [$query, $priceParams] = $this->buildQuery($request);
        $result = $this->catalog->products($query);

        return $this->render('shop/index.html.twig', [
            'products' => $result['items'],
            'categories' => $this->catalog->rootCategories(),
            'priceRange' => $result['priceRange'],
            'priceParams' => $priceParams,
            'currentSort' => $query['sort'] ?? '',
        ]);
    }

    #[Route('/category/{slug}', name: 'app_category_show')]
    public function showCategory(string $slug, Request $request): Response
    {
        $category = $this->catalog->category($slug);
        if (null === $category) {
            throw $this->createNotFoundException('Category not found');
        }

        [$query, $priceParams] = $this->buildQuery($request, categorySlug: $slug);
        $result = $this->catalog->products($query);

        return $this->render('shop/index.html.twig', [
            'products' => $result['items'],
            'categories' => $this->catalog->rootCategories(),
            'currentCategory' => $category,
            'priceRange' => $result['priceRange'],
            'priceParams' => $priceParams,
            'currentSort' => $query['sort'] ?? '',
        ]);
    }

    #[Route('/brand/{slug}', name: 'shop_brand')]
    public function showBrand(string $slug, Request $request): Response
    {
        $brand = $this->catalog->brand($slug);
        if (null === $brand) {
            throw $this->createNotFoundException('Brand not found');
        }

        [$query, $priceParams] = $this->buildQuery($request, brandSlug: $slug);
        $result = $this->catalog->products($query);

        return $this->render('shop/index.html.twig', [
            'products' => $result['items'],
            'categories' => $this->catalog->rootCategories(),
            'currentBrand' => $brand,
            'priceRange' => $result['priceRange'],
            'priceParams' => $priceParams,
            'currentSort' => $query['sort'] ?? '',
        ]);
    }

    #[Route('/brand/{brandSlug}/category/{categorySlug}', name: 'shop_brand_category')]
    public function showBrandCategory(string $brandSlug, string $categorySlug, Request $request): Response
    {
        $brand = $this->catalog->brand($brandSlug);
        $category = $this->catalog->category($categorySlug);
        if (null === $brand || null === $category) {
            throw $this->createNotFoundException();
        }

        [$query, $priceParams] = $this->buildQuery($request, categorySlug: $categorySlug, brandSlug: $brandSlug);
        $result = $this->catalog->products($query);

        return $this->render('shop/index.html.twig', [
            'products' => $result['items'],
            'categories' => $this->catalog->rootCategories(),
            'currentBrand' => $brand,
            'currentCategory' => $category,
            'priceRange' => $result['priceRange'],
            'priceParams' => $priceParams,
            'currentSort' => $query['sort'] ?? '',
        ]);
    }

    #[Route('/categories', name: 'app_categories')]
    public function categories(): Response
    {
        return $this->render('shop/categories.html.twig', [
            'categories' => $this->catalog->rootCategories(),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{min: float|null, max: float|null}}
     */
    private function buildQuery(Request $request, ?string $categorySlug = null, ?string $brandSlug = null): array
    {
        $priceMinEur = $request->query->get('price_min');
        $priceMaxEur = $request->query->get('price_max');

        $query = [
            'sort' => $request->query->get('sort', ''),
            'categorySlug' => $categorySlug,
            'brandSlug' => $brandSlug,
        ];
        $priceParams = ['min' => null, 'max' => null];

        if (null !== $priceMinEur && '' !== $priceMinEur) {
            $query['priceMin'] = (int) round((float) $priceMinEur * 100);
            $priceParams['min'] = (float) $priceMinEur;
        }
        if (null !== $priceMaxEur && '' !== $priceMaxEur) {
            $query['priceMax'] = (int) round((float) $priceMaxEur * 100);
            $priceParams['max'] = (float) $priceMaxEur;
        }

        return [$query, $priceParams];
    }
}
