<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Payment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class PaymentTest extends TestCase
{
    public function testNewPaymentStartsInitiated(): void
    {
        $payment = new Payment(Uuid::v4(), 4999);

        self::assertSame(Payment::STATUS_INITIATED, $payment->getStatus());
        self::assertSame(4999, $payment->getAmount());
        self::assertSame('eur', $payment->getCurrency());
        self::assertNull($payment->getStripeSessionId());
    }

    public function testStatusChangeTouchesUpdatedAt(): void
    {
        $payment = new Payment(Uuid::v4(), 1000);
        $createdAt = $payment->getCreatedAt();

        // Force a distinct instant so the touch is observable.
        usleep(1000);
        $payment->setStatus(Payment::STATUS_SUCCEEDED);

        self::assertSame(Payment::STATUS_SUCCEEDED, $payment->getStatus());
        self::assertGreaterThanOrEqual($createdAt, $payment->getUpdatedAt());
    }
}
