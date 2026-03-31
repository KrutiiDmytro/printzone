<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiCheckController extends AbstractController
{
    #[Route('/api/login_check', name: 'api_login_check_action', methods: ['GET', 'POST'])]
    public function check(): JsonResponse
    {
        // Если запрос дошел сюда, значит JWT токен валидный (firewall пропустил)
        $user = $this->getUser();
        
        return new JsonResponse([
            'message' => 'Token is valid',
            'user' => $user ? $user->getUserIdentifier() : null
        ]);
    }
}