<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

/**
 * Dispatched after a successful interactive (session) login. Other modules react
 * to it instead of being called directly — e.g. Cart migrates the guest session
 * cart to the authenticated user's cart.
 */
final readonly class UserLoggedIn
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }
}
