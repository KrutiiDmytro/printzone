<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\LoginListener;
use App\Service\CartService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginListenerTest extends TestCase
{
    private LoginListener $listener;
    private $cartService;
    private $urlGenerator;

    protected function setUp(): void
    {
        $this->cartService = $this->createMock(CartService::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->listener = new LoginListener(
            $this->cartService,
            $this->urlGenerator
        );
    }

    public function testOnLoginSuccessMigratesCart(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $this->cartService
            ->expects($this->once())
            ->method('migrateSessionToDatabase');

        $this->urlGenerator
            ->method('generate')
            ->with('app_home')
            ->willReturn('/');

        $response = new RedirectResponse('/');
        $event->method('setResponse')->willReturnCallback(function ($response) {
            $this->assertInstanceOf(RedirectResponse::class, $response);
        });

        $this->listener->onLoginSuccess($event);
    }

    public function testOnLoginSuccessRedirectsAdminToAdminDashboard(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $this->urlGenerator
            ->method('generate')
            ->with('admin_dashboard')
            ->willReturn('/admin');

        $response = new RedirectResponse('/admin');
        $event->expects($this->once())
            ->method('setResponse')
            ->with($this->isInstanceOf(RedirectResponse::class));

        $this->listener->onLoginSuccess($event);
    }
}
