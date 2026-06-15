<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ProductController extends AbstractController
{
    #[Route('/bestseller', name: 'app_bestseller')]
    public function bestseller(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findFeatured(12);

        return $this->render('product/bestseller.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show')]
    public function show(string $id, ProductRepository $productRepository): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Product not found');
        }

        $product = $productRepository->find(Uuid::fromString($id));

        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
