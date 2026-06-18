<?php

namespace App\Twig;

use App\Catalog\Client\CatalogClient;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private CatalogClient $catalogClient,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_categories', [$this, 'getCategories']),
        ];
    }

    /**
     * @return \App\Catalog\View\CategoryView[]
     */
    public function getCategories(): array
    {
        return $this->catalogClient->rootCategories();
    }
}
