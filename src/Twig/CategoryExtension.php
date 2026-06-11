<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // Регистрируем функцию get_categories для использования в шаблонах
            new TwigFunction('get_categories', [$this, 'getCategories']),
        ];
    }

    public function getCategories(): array
    {
        return $this->categoryRepository->findAllRootCategories();
    }
}
