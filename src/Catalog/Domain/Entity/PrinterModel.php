<?php

namespace App\Catalog\Domain\Entity;

use App\Repository\PrinterModelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PrinterModelRepository::class)]
#[ORM\Table(name: 'printer_models', schema: 'catalog')]
class PrinterModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    // Brand lives in catalog-service now; we keep snapshots (slug is the storefront key).
    #[ORM\Column(length: 255)]
    private string $brandSlug;

    #[ORM\Column(length: 255)]
    private string $brandName;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getBrandSlug(): string
    {
        return $this->brandSlug;
    }

    public function setBrandSlug(string $brandSlug): static
    {
        $this->brandSlug = $brandSlug;

        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): static
    {
        $this->brandName = $brandName;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
