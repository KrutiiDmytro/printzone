<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ProductImageService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductImageService $productImageService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_image_url', $this->productImageUrl(...)),
        ];
    }

    public function productImageUrl(?string $image): string
    {
        return $this->productImageService->getUrlForDisplay($image);
    }
}