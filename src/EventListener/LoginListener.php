<?php

namespace App\EventListener;

use App\Service\CartService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
class LoginListener
{
    public function __construct(
        private CartService $cartService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // Если вход происходит через firewall 'login' (наш API для токенов),
        // то мы ничего не делаем и позволяем JWT вернуть JSON.
        if ('login' === $event->getFirewallName() || 'api' === $event->getFirewallName()) {
            return;
        }

        // Переносим гостевую корзину из сессии в БД для залогиненного пользователя
        $this->cartService->migrateSessionToDatabase();

        // Получаем пользователя
        $user = $event->getUser();

        // Проверяем роли и перенаправляем соответственно
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            // Администратор - перенаправляем в админ-панель
            $response = new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
            $event->setResponse($response);
        } else {
            // Обычный пользователь - перенаправляем на главную
            $response = new RedirectResponse($this->urlGenerator->generate('app_home'));
            $event->setResponse($response);
        }
    }
}
