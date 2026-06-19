<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Catalog\Client\CatalogAdminClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalog admin proxied over HTTP to catalog-service (the source of truth).
 * The monolith no longer stores catalog data, so these pages read/write the
 * service via CatalogAdminClient instead of Doctrine + EasyAdmin.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/catalog')]
final class CatalogAdminController extends AbstractController
{
    public function __construct(private readonly CatalogAdminClient $catalog)
    {
    }

    // ----- Products -----

    #[Route('/products', name: 'admin_catalog_products', methods: ['GET'])]
    public function products(): Response
    {
        return $this->render('admin/catalog/products.html.twig', ['products' => $this->catalog->products()]);
    }

    #[Route('/products/new', name: 'admin_catalog_product_new', methods: ['GET', 'POST'])]
    public function productNew(Request $request): Response
    {
        return $this->handleProductForm($request, null);
    }

    #[Route('/products/{id}/edit', name: 'admin_catalog_product_edit', methods: ['GET', 'POST'])]
    public function productEdit(Request $request, string $id): Response
    {
        $product = $this->catalog->product($id);
        if (null === $product) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $this->handleProductForm($request, $product);
    }

    #[Route('/products/{id}/delete', name: 'admin_catalog_product_delete', methods: ['POST'])]
    public function productDelete(Request $request, string $id): Response
    {
        return $this->handleDelete($request, 'products', $id, 'admin_catalog_products');
    }

    /**
     * @param array<string, mixed>|null $product
     */
    private function handleProductForm(Request $request, ?array $product): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('catalog_admin', $request->request->getString('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');

                return $this->redirectToRoute('admin_catalog_products');
            }

            $payload = [
                'name' => $request->request->getString('name'),
                'price' => (int) round((float) $request->request->getString('price') * 100),
                'stock' => $request->request->getInt('stock'),
                'category' => $request->request->getString('category'),
                'brand' => $request->request->getString('brand') ?: null,
                'description' => $request->request->getString('description') ?: null,
                'image' => $request->request->getString('image') ?: null,
                'isFeatured' => $request->request->getBoolean('isFeatured'),
            ];

            try {
                if (null === $product) {
                    $this->catalog->create('products', $payload);
                    $this->addFlash('success', 'Product created.');
                } else {
                    $this->catalog->update('products', (string) $product['id'], $payload);
                    $this->addFlash('success', 'Product updated.');
                }

                return $this->redirectToRoute('admin_catalog_products');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Save failed: '.$e->getMessage());
            }
        }

        return $this->render('admin/catalog/product_form.html.twig', [
            'product' => $product,
            'categories' => $this->catalog->categories(),
            'brands' => $this->catalog->brands(),
        ]);
    }

    // ----- Categories -----

    #[Route('/categories', name: 'admin_catalog_categories', methods: ['GET'])]
    public function categories(): Response
    {
        return $this->render('admin/catalog/categories.html.twig', ['categories' => $this->catalog->categories()]);
    }

    #[Route('/categories/new', name: 'admin_catalog_category_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $request): Response
    {
        return $this->handleCategoryForm($request, null);
    }

    #[Route('/categories/{id}/edit', name: 'admin_catalog_category_edit', methods: ['GET', 'POST'])]
    public function categoryEdit(Request $request, string $id): Response
    {
        $category = $this->findById($this->catalog->categories(), $id);
        if (null === $category) {
            throw $this->createNotFoundException('Category not found.');
        }

        return $this->handleCategoryForm($request, $category);
    }

    #[Route('/categories/{id}/delete', name: 'admin_catalog_category_delete', methods: ['POST'])]
    public function categoryDelete(Request $request, string $id): Response
    {
        return $this->handleDelete($request, 'categories', $id, 'admin_catalog_categories');
    }

    /**
     * @param array<string, mixed>|null $category
     */
    private function handleCategoryForm(Request $request, ?array $category): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('catalog_admin', $request->request->getString('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');

                return $this->redirectToRoute('admin_catalog_categories');
            }

            $payload = [
                'name' => $request->request->getString('name'),
                'slug' => $request->request->getString('slug'),
                'parent' => $request->request->getString('parent') ?: null,
            ];

            try {
                if (null === $category) {
                    $this->catalog->create('categories', $payload);
                    $this->addFlash('success', 'Category created.');
                } else {
                    $this->catalog->update('categories', (string) $category['id'], $payload);
                    $this->addFlash('success', 'Category updated.');
                }

                return $this->redirectToRoute('admin_catalog_categories');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Save failed: '.$e->getMessage());
            }
        }

        return $this->render('admin/catalog/category_form.html.twig', [
            'category' => $category,
            'categories' => $this->catalog->categories(),
        ]);
    }

    // ----- Brands -----

    #[Route('/brands', name: 'admin_catalog_brands', methods: ['GET'])]
    public function brands(): Response
    {
        return $this->render('admin/catalog/brands.html.twig', ['brands' => $this->catalog->brands()]);
    }

    #[Route('/brands/new', name: 'admin_catalog_brand_new', methods: ['GET', 'POST'])]
    public function brandNew(Request $request): Response
    {
        return $this->handleBrandForm($request, null);
    }

    #[Route('/brands/{id}/edit', name: 'admin_catalog_brand_edit', methods: ['GET', 'POST'])]
    public function brandEdit(Request $request, string $id): Response
    {
        $brand = $this->findById($this->catalog->brands(), $id);
        if (null === $brand) {
            throw $this->createNotFoundException('Brand not found.');
        }

        return $this->handleBrandForm($request, $brand);
    }

    #[Route('/brands/{id}/delete', name: 'admin_catalog_brand_delete', methods: ['POST'])]
    public function brandDelete(Request $request, string $id): Response
    {
        return $this->handleDelete($request, 'brands', $id, 'admin_catalog_brands');
    }

    /**
     * @param array<string, mixed>|null $brand
     */
    private function handleBrandForm(Request $request, ?array $brand): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('catalog_admin', $request->request->getString('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');

                return $this->redirectToRoute('admin_catalog_brands');
            }

            $payload = [
                'name' => $request->request->getString('name'),
                'slug' => $request->request->getString('slug'),
                'color' => $request->request->getString('color') ?: null,
            ];

            try {
                if (null === $brand) {
                    $this->catalog->create('brands', $payload);
                    $this->addFlash('success', 'Brand created.');
                } else {
                    $this->catalog->update('brands', (string) $brand['id'], $payload);
                    $this->addFlash('success', 'Brand updated.');
                }

                return $this->redirectToRoute('admin_catalog_brands');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Save failed: '.$e->getMessage());
            }
        }

        return $this->render('admin/catalog/brand_form.html.twig', ['brand' => $brand]);
    }

    // ----- Shared -----

    private function handleDelete(Request $request, string $resource, string $id, string $redirect): Response
    {
        if (!$this->isCsrfTokenValid('catalog_admin', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
        } else {
            try {
                $this->catalog->delete($resource, $id);
                $this->addFlash('success', 'Deleted.');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Delete failed: '.$e->getMessage());
            }
        }

        return $this->redirectToRoute($redirect);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, mixed>|null
     */
    private function findById(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }
}
