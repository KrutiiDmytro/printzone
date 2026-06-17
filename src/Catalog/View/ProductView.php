<?php

declare(strict_types=1);

namespace App\Catalog\View;

/**
 * Read-only view of a Catalog Service product. Mirrors the Product entity getters
 * used by storefront templates. `getAttributes()` is empty in this MVP (product
 * attributes stay in the monolith and are not exposed by the service yet).
 */
final class ProductView
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $name,
        private readonly ?string $description,
        private readonly ?int $price,
        private readonly int $stock,
        private readonly ?string $image,
        private readonly bool $isFeatured,
        private readonly ?CategoryView $category,
        private readonly ?BrandView $brand,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $category = isset($data['category']) && is_array($data['category']) ? CategoryView::fromArray($data['category']) : null;
        $brand = isset($data['brand']) && is_array($data['brand']) ? BrandView::fromArray($data['brand']) : null;

        return new self(
            (string) ($data['id'] ?? ''),
            isset($data['name']) ? (string) $data['name'] : null,
            isset($data['description']) ? (string) $data['description'] : null,
            isset($data['price']) ? (int) $data['price'] : null,
            (int) ($data['stock'] ?? 0),
            isset($data['image']) ? (string) $data['image'] : null,
            (bool) ($data['isFeatured'] ?? false),
            $category,
            $brand,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function getCategory(): ?CategoryView
    {
        return $this->category;
    }

    public function getBrand(): ?BrandView
    {
        return $this->brand;
    }

    /**
     * @return array<int, mixed>
     */
    public function getAttributes(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
