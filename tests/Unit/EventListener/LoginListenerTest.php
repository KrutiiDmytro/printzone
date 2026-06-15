<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\LoginListener;
use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserLoggedIn;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Uid\Uuid;

class LoginListenerTest extends TestCase
{
    private LoginListener $listener;
    private $eventDispatcher;
    private $urlGenerator;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->listener = new LoginListener(
            $this->eventDispatcher,
            $this->urlGenerator
        );
    }

    public function testOnLoginSuccessDispatchesUserLoggedIn(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v4());
        $user->method('getUserIdentifier')->willReturn('user@example.com');
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(UserLoggedIn::class));

        $this->urlGenerator
            ->method('generate')
            ->with('app_home')
            ->willReturn('/');

        $event->method('setResponse')->willReturnCallback(function ($response): void {
            $this->assertInstanceOf(RedirectResponse::class, $response);
        });

        $this->listener->onLoginSuccess($event);
    }

    public function testOnLoginSuccessRedirectsAdminToAdminDashboard(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v4());
        $user->method('getUserIdentifier')->willReturn('admin@example.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $this->urlGenerator
            ->method('generate')
            ->with('admin_dashboard')
            ->willReturn('/admin');

        $event->expects($this->once())
            ->method('setResponse')
            ->with($this->isInstanceOf(RedirectResponse::class));

        $this->listener->onLoginSuccess($event);
    }
}
