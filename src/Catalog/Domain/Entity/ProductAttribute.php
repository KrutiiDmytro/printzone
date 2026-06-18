<?php

namespace App\Catalog\Domain\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_attributes', schema: 'catalog')]
#[ORM\Index(columns: ['product_id'], name: 'idx_product_attributes_product_id')]
#[ApiResource(
    normalizationContext: ['groups' => ['product_attribute:read']],
    denormalizationContext: ['groups' => ['product_attribute:write']]
)]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['product_attribute:read', 'product:read'])]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Groups(['product_attribute:read', 'product_attribute:write', 'product:read', 'product:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product_attribute:read', 'product_attribute:write', 'product:read', 'product:write'])]
    private ?string $value = null;

    #[ORM\ManyToOne(inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product_attribute:read', 'product_attribute:write'])]
    private ?Product $product = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
