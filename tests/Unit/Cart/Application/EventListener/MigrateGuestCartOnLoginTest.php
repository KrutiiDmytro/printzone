<?php

namespace App\Tests\Unit\Cart\Application\EventListener;

use App\Cart\Application\EventListener\MigrateGuestCartOnLogin;
use App\Service\CartService;
use App\User\Domain\Event\UserLoggedIn;
use PHPUnit\Framework\TestCase;

class MigrateGuestCartOnLoginTest extends TestCase
{
    public function testInvokeMigratesGuestCart(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('migrateSessionToDatabase');

        $listener = new MigrateGuestCartOnLogin($cartService);
        $listener(new UserLoggedIn('00000000-0000-0000-0000-000000000001', 'user@example.com'));
    }
}
