<?php

declare(strict_types=1);

namespace App\Cart\Application\EventListener;

use App\Service\CartService;
use App\User\Domain\Event\UserLoggedIn;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Reacts to a successful login by migrating the guest session cart into the
 * authenticated user's database cart. Keeps the Cart concern out of the
 * security/login flow — the User module only emits UserLoggedIn.
 */
#[AsEventListener(event: UserLoggedIn::class)]
final class MigrateGuestCartOnLogin
{
    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    public function __invoke(UserLoggedIn $event): void
    {
        $this->cartService->migrateSessionToDatabase();
    }
}
