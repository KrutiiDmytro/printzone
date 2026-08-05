<?php

namespace App\DataFixtures;

use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Minimal dev/test seed. Prod is NOT seeded — orders are created at runtime.
 */
class OrderFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (['PAID', 'PENDING'] as $i => $status) {
            $order = new Order();
            $order->setUserId(Uuid::v4());
            $order->setUserEmail(sprintf('demo%d@example.com', $i + 1));
            $order->setStatus($status);
            $order->setTotalAmount(1999 * ($i + 1));

            $item = new OrderItem();
            $item->setOrderRef($order);
            $item->setProductId(Uuid::v4());
            $item->setProductName('Demo Cartridge');
            $item->setPrice(1999);
            $item->setQuantity($i + 1);
            $order->getItems()->add($item);

            $manager->persist($order);
        }

        $manager->flush();
    }
}
