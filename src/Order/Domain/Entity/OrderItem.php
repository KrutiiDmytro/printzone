<?php

namespace App\Order\Domain\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Catalog\Domain\Entity\Product;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
#[ORM\Index(columns: ['order_ref_id'], name: 'idx_order_items_order_ref_id')]
#[ORM\Index(columns: ['product_id'], name: 'idx_order_items_product_id')]
#[ApiResource(
    normalizationContext: ['groups' => ['order_item:read']],
    denormalizationContext: ['groups' => ['order_item:write']]
)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order_item:read', 'order:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_item:write'])]
    private ?Order $orderRef = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_item:read', 'order:read', 'order:write'])]
    private ?Product $product = null;

    #[ORM\Column]
    #[Groups(['order_item:read', 'order:read', 'order:write'])]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(['order_item:read', 'order:read', 'order_item:write', 'order:write'])]
    private ?int $price = null; // Snapshot of price at purchase time

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderRef(): ?Order
    {
        return $this->orderRef;
    }

    public function setOrderRef(?Order $orderRef): static
    {
        $this->orderRef = $orderRef;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (x%d)', $this->product?->getName() ?? 'Unknown Product', $this->quantity ?? 0);
    }
}
