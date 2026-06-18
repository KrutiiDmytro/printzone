<?php

declare(strict_types=1);

namespace App\Catalog\View;

/**
 * Read-only view of a Catalog Service brand. Exposes entity-like getters so
 * existing storefront templates work unchanged after the cutover.
 */
final class BrandView
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $slug,
        private readonly ?string $color,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['slug'] ?? ''),
            isset($data['color']) ? (string) $data['color'] : null,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
