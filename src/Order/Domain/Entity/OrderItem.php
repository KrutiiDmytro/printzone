<?php

namespace App\Order\Domain\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

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
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['order_item:read', 'order:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_item:write'])]
    private ?Order $orderRef = null;

    // Cross-service reference to Catalog (no FK). Name + price are snapshots at order time.
    #[ORM\Column(type: 'uuid')]
    #[Groups(['order_item:read', 'order:read', 'order:write'])]
    private ?Uuid $productId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order_item:read', 'order:read', 'order:write'])]
    private ?string $productName = null;

    #[ORM\Column]
    #[Groups(['order_item:read', 'order:read', 'order:write'])]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(['order_item:read', 'order:read', 'order_item:write', 'order:write'])]
    private ?int $price = null; // Snapshot of price at purchase time

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
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

    public function getProductId(): ?Uuid
    {
        return $this->productId;
    }

    public function setProductId(Uuid $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

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
        return sprintf('%s (x%d)', $this->productName ?? 'Unknown Product', $this->quantity ?? 0);
    }
}
