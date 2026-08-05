<?php

declare(strict_types=1);

namespace App\Tests\Functional\Saga;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\StockReservation;
use App\Messaging\Domain\IntegrationEvent;
use App\MessageHandler\OrderEventHandler;
use App\Repository\StockReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class OrderEventHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private OrderEventHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // Build the handler directly with EM-backed repositories — it is a
        // private autoconfigured service, not exposed by the test container.
        $this->handler = new OrderEventHandler(
            $this->em->getRepository(StockReservation::class),
            $this->em->getRepository(Product::class),
            $this->em,
            new NullLogger(),
        );
    }

    public function testOrderCreatedReservesHeldAndIsIdempotent(): void
    {
        $orderId = Uuid::v4();
        $productId = $this->product(10);

        $event = $this->event('OrderCreated', $orderId, [['productId' => (string) $productId, 'quantity' => 3]]);
        ($this->handler)($event);
        ($this->handler)($event); // at-least-once re-delivery

        $reservations = $this->reservations()->findByOrder($orderId);
        self::assertCount(1, $reservations);
        self::assertSame(StockReservation::STATUS_HELD, $reservations[0]->getStatus());
        self::assertSame(3, $reservations[0]->getQuantity());
        // HELD does not touch stock yet.
        self::assertSame(10, $this->stockOf($productId));
    }

    public function testOrderPaidCommitsAndDecrementsStockOnce(): void
    {
        $orderId = Uuid::v4();
        $productId = $this->product(10);

        ($this->handler)($this->event('OrderCreated', $orderId, [['productId' => (string) $productId, 'quantity' => 4]]));
        ($this->handler)($this->event('OrderPaid', $orderId));
        ($this->handler)($this->event('OrderPaid', $orderId)); // re-delivery must not double-decrement

        $reservations = $this->reservations()->findByOrder($orderId);
        self::assertSame(StockReservation::STATUS_COMMITTED, $reservations[0]->getStatus());
        self::assertSame(6, $this->stockOf($productId)); // 10 - 4, once
    }

    public function testOrderCancelledReleasesHeldAndLeavesStock(): void
    {
        $orderId = Uuid::v4();
        $productId = $this->product(10);

        ($this->handler)($this->event('OrderCreated', $orderId, [['productId' => (string) $productId, 'quantity' => 2]]));
        ($this->handler)($this->event('OrderCancelled', $orderId));

        $reservations = $this->reservations()->findByOrder($orderId);
        self::assertSame(StockReservation::STATUS_RELEASED, $reservations[0]->getStatus());
        self::assertSame(10, $this->stockOf($productId)); // released, never committed
    }

    /**
     * @param list<array{productId: string, quantity: int}> $items
     */
    private function event(string $name, Uuid $orderId, array $items = []): IntegrationEvent
    {
        return new IntegrationEvent('order', $name, ['orderId' => (string) $orderId, 'items' => $items]);
    }

    private function product(int $stock): Uuid
    {
        $brand = (new Brand())->setName('B')->setSlug('b-'.uniqid())->setColor('#000000');
        $category = (new Category())->setName('C')->setSlug('c-'.uniqid());
        $this->em->persist($brand);
        $this->em->persist($category);

        $product = (new Product())->setName('P')->setCategory($category)->setBrand($brand)->setPrice(1000)->setStock($stock);
        $this->em->persist($product);
        $this->em->flush();

        return $product->getId();
    }

    private function stockOf(Uuid $productId): int
    {
        $this->em->clear();

        return $this->em->getRepository(Product::class)->find($productId)->getStock();
    }

    private function reservations(): StockReservationRepository
    {
        return $this->em->getRepository(StockReservation::class);
    }
}
