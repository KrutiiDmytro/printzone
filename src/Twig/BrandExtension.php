<?php

namespace App\Twig;

use App\Repository\BrandRepository;
use App\Repository\PrinterModelRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BrandExtension extends AbstractExtension
{
    public function __construct(
        private BrandRepository $brandRepository,
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

    public function getAllBrands(): array
    {
        return $this->brandRepository->findAll();
    }

    public function getBrandModels(string $brandSlug): array
    {
        return $this->printerModelRepository->findByBrandSlug($brandSlug);
    }
}
