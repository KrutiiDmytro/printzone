<?php

namespace App\Tests\Unit;

use App\MessageHandler\NotificationHandler;
use App\Messaging\Domain\IntegrationEvent;
use App\Notification\EmailChannel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class NotificationHandlerTest extends TestCase
{
    private function handler(MailerInterface $mailer): NotificationHandler
    {
        return new NotificationHandler(
            new EmailChannel($mailer, 'shop@example.com'),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testOrderPaidSendsReceiptToCustomer(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email): bool {
                $to = $email->getTo();

                return 1 === \count($to)
                    && 'buyer@example.com' === $to[0]->getAddress()
                    && 'Дякуємо! Ваше замовлення оплачено' === $email->getSubject()
                    && 'email/order_paid.html.twig' === $email->getHtmlTemplate()
                    && ['orderId' => 'o-1', 'totalAmount' => 5000] === $email->getContext();
            }));

        $this->handler($mailer)(new IntegrationEvent('order', 'OrderPaid', [
            'orderId' => 'o-1',
            'userEmail' => 'buyer@example.com',
            'totalAmount' => 5000,
        ]));
    }

    public function testOrderPaidWithoutEmailSendsNothing(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->handler($mailer)(new IntegrationEvent('order', 'OrderPaid', ['orderId' => 'o-1']));
    }

    public function testUnknownEventIsNoOp(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->handler($mailer)(new IntegrationEvent('catalog', 'ProductDeleted', ['productId' => 'p-1']));
    }
}
