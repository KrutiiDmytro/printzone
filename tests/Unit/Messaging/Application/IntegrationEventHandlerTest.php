<?php

namespace App\Tests\Unit\Messaging\Application;

use App\Messaging\Application\IntegrationEventHandler;
use App\Messaging\Domain\IntegrationEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class IntegrationEventHandlerTest extends TestCase
{
    private function makeHandler(MailerInterface $mailer): IntegrationEventHandler
    {
        return new IntegrationEventHandler($mailer, $this->createMock(LoggerInterface::class), 'shop@example.com');
    }

    public function testOrderPaidSendsReceiptToCustomer(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email): bool {
                $to = $email->getTo();

                return 1 === \count($to) && 'buyer@example.com' === $to[0]->getAddress();
            }));

        $this->makeHandler($mailer)(new IntegrationEvent('order', 'OrderPaid', [
            'orderId' => 1,
            'userEmail' => 'buyer@example.com',
            'totalAmount' => 5000,
        ]));
    }

    public function testNonOrderPaidEventSendsNoEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->makeHandler($mailer)(new IntegrationEvent('user', 'UserRegistered', ['email' => 'x@example.com']));
    }

    public function testOrderPaidWithoutEmailSendsNothing(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $this->makeHandler($mailer)(new IntegrationEvent('order', 'OrderPaid', ['orderId' => 1]));
    }
}
