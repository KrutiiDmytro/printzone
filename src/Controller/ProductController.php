<?php

namespace App\Controller;

use App\Catalog\Client\CatalogClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ProductController extends AbstractController
{
    #[Route('/bestseller', name: 'app_bestseller')]
    public function bestseller(CatalogClient $catalog): Response
    {
        return $this->render('product/bestseller.html.twig', [
            'products' => $catalog->featured(12),
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show')]
    public function show(string $id, CatalogClient $catalog): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Product not found');
        }

        $product = $catalog->product($id);

        if (null === $product) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
