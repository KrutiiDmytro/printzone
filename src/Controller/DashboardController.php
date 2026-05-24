<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $featuredProducts = $productRepository->findFeatured(8);
        $categories = $categoryRepository->findAllRootCategories();
        $bestsellerProducts = $productRepository->findLatest(6);

        return $this->render('home.html.twig', [
            'featuredProducts' => $featuredProducts,
            'bestsellerProducts' => $bestsellerProducts,
            'categories' => $categories,
        ]);
    }
}