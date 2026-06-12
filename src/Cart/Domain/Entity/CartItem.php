<?php

namespace App\Cart\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cart_items')]
#[ORM\Index(columns: ['cart_id'], name: 'idx_cart_items_cart_id')]
#[ORM\Index(columns: ['product_id'], name: 'idx_cart_items_product_id')]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    // Cross-service reference to Catalog (no FK). Name + price are snapshots taken when added.
    #[ORM\Column(type: 'integer')]
    private int $productId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $productName;

    #[ORM\Column(type: 'integer')]
    private int $price;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        $this->cart = $cart;

        return $this;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->price * $this->quantity;
    }
}
