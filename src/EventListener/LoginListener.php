<?php

namespace App\EventListener;

use App\User\Domain\Entity\User;
use App\User\Domain\Event\UserLoggedIn;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
class LoginListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // Если вход происходит через firewall 'login' (наш API для токенов),
        // то мы ничего не делаем и позволяем JWT вернуть JSON.
        if ('login' === $event->getFirewallName() || 'api' === $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();

        // Оповещаем другие модули о входе (Cart переносит гостевую корзину в БД).
        if ($user instanceof User) {
            $this->eventDispatcher->dispatch(new UserLoggedIn((string) $user->getId(), $user->getUserIdentifier()));
        }

        // Проверяем роли и перенаправляем соответственно
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_dashboard')));
        } else {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_home')));
        }
    }
}
