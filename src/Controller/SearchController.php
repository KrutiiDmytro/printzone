<?php

namespace App\Controller;

use App\Repository\PrinterModelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search/autocomplete', name: 'search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, PrinterModelRepository $repo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $models = $repo->searchByName($q);

        return $this->json(array_map(fn ($m) => [
            'name' => $m->getName(),
            'brandName' => $m->getBrand()->getName(),
            'brandSlug' => $m->getBrand()->getSlug(),
        ], $models));
    }
}
