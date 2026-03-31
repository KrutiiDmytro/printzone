<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'app_shop')]
    public function index(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $products = $productRepository->findAllAvailable();
        $categories = $categoryRepository->findAllRootCategories();

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    #[Route('/category/{slug}', name: 'app_category_show')]
    public function showCategory(
        string $slug,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        $category = $categoryRepository->findBySlug($slug);
        
        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }
        
        $products = $productRepository->findByCategory($category);
        $categories = $categoryRepository->findAllRootCategories();
        
        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'currentCategory' => $category,
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
}
