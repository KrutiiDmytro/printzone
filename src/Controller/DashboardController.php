<?php

namespace App\Controller;

use App\Catalog\Client\CatalogClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(CatalogClient $catalog): Response
    {
        return $this->render('home.html.twig', [
            'featuredProducts' => $catalog->featured(8),
            'bestsellerProducts' => $catalog->products(['limit' => 6])['items'],
            'categories' => $catalog->rootCategories(),
        ]);
    }
}
