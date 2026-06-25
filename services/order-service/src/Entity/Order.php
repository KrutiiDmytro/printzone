<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(columns: ['created_at'], name: 'idx_orders_created_at')]
#[ORM\Index(columns: ['status'], name: 'idx_orders_status')]
#[ORM\Index(columns: ['user_id'], name: 'idx_orders_user_id')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['order:read'])]
    private Uuid $id;

    // Cross-service reference to the User domain (no FK). userEmail is a snapshot.
    #[ORM\Column(type: 'uuid')]
    #[Groups(['order:read'])]
    private ?Uuid $userId = null;

    #[ORM\Column(length: 180)]
    #[Groups(['order:read'])]
    private ?string $userEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['order:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    #[Groups(['order:read'])]
    private ?string $status = 'PENDING';

    #[ORM\Column]
    #[Groups(['order:read'])]
    private ?int $totalAmount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\OneToMany(mappedBy: 'orderRef', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['order:read'])]
    private Collection $items;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function setUserId(Uuid $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Item lines as a plain snapshot for integration-event payloads
     * (OrderCreated/OrderCancelled), so the Catalog Saga can reserve/release stock.
     *
     * @return list<array{productId: string, quantity: int}>
     */
    public function toEventItems(): array
    {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = [
                'productId' => (string) $item->getProductId(),
                'quantity' => (int) $item->getQuantity(),
            ];
        }

        return $items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrderRef($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrderRef() === $this) {
                $item->setOrderRef(null);
            }
        }

        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;

        return $this;
    }
}
