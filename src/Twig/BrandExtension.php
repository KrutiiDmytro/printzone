<?php

namespace App\Twig;

use App\Catalog\Client\CatalogClient;
use App\Repository\PrinterModelRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BrandExtension extends AbstractExtension
{
    public function __construct(
        private CatalogClient $catalogClient,
        private PrinterModelRepository $printerModelRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('all_brands', [$this, 'getAllBrands']),
            new TwigFunction('brand_models', [$this, 'getBrandModels']),
        ];
    }

    /**
     * @return \App\Catalog\View\BrandView[]
     */
    public function getAllBrands(): array
    {
        return $this->catalogClient->brands();
    }

    public function getBrandModels(string $brandSlug): array
    {
        // Printer models stay in the monolith (printer-finder feature).
        return $this->printerModelRepository->findByBrandSlug($brandSlug);
    }
}
