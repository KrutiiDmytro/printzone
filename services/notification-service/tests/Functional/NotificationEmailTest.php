<?php

namespace App\Tests\Functional;

use App\Messaging\Domain\IntegrationEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Boots the real container (real Twig + Mailer with the null transport) so the
 * order_paid template actually renders and the message is captured end-to-end.
 * Events are dispatched on the default bus exactly as the worker would after
 * consuming them off the broker (IntegrationEvent has no async routing, so the
 * handler runs synchronously).
 */
class NotificationEmailTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private function dispatch(string $aggregate, string $eventName, array $payload): void
    {
        static::getContainer()->get(MessageBusInterface::class)
            ->dispatch(new IntegrationEvent($aggregate, $eventName, $payload));
    }

    public function testOrderPaidRendersAndSendsReceipt(): void
    {
        self::bootKernel();

        $this->dispatch('order', 'OrderPaid', [
            'orderId' => 'ord-42',
            'userEmail' => 'buyer@example.com',
            'totalAmount' => 12345,
        ]);

        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertEmailAddressContains($email, 'To', 'buyer@example.com');
        self::assertEmailHtmlBodyContains($email, 'ord-42');
        self::assertEmailHtmlBodyContains($email, '123.45');
    }

    public function testOrderPaidWithoutEmailSendsNothing(): void
    {
        self::bootKernel();

        $this->dispatch('order', 'OrderPaid', ['orderId' => 'ord-43']);

        self::assertEmailCount(0);
    }

    public function testUnknownEventSendsNothing(): void
    {
        self::bootKernel();

        $this->dispatch('delivery', 'ShipmentCreated', ['shipmentId' => 's-1', 'orderId' => 'ord-44']);

        self::assertEmailCount(0);
    }
}
