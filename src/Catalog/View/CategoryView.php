<?php

declare(strict_types=1);

namespace App\Catalog\View;

/**
 * Read-only view of a Catalog Service category. `getProducts()` returns an empty
 * list in this MVP (per-category product fetching over HTTP is deferred).
 */
final class CategoryView
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $name,
        private readonly ?string $slug,
        private readonly ?string $parentId,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            isset($data['name']) ? (string) $data['name'] : null,
            isset($data['slug']) ? (string) $data['slug'] : null,
            isset($data['parentId']) ? (string) $data['parentId'] : null,
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @return array<int, mixed>
     */
    public function getProducts(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
