<?php

namespace App\Controller;

use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager
    ): Response {
        // Если пользователь уже залогинен, перенаправляем на главную
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;

        // Обработка POST запроса (отправка формы)
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {
                $error = 'Invalid CSRF token.';
                return $this->render('registration/register.html.twig', ['error' => $error]);
            }

            $fullName = $request->request->get('fullName');
            $email = $request->request->get('email');
            $password = $request->request->get('password');

            // Валидация
            if (empty($fullName) || empty($email) || empty($password)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                // Проверяем, не существует ли уже пользователь с таким email
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                
                if ($existingUser) {
                    $error = 'User with this email already exists.';
                } else {
                    // Создаем нового пользователя
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName);
                    $user->setRoles(['ROLE_USER']);
                    
                    // Хешируем пароль
                    $hashedPassword = $userPasswordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);
                    
                    // Сохраняем в базу данных
                    $entityManager->persist($user);
                    $entityManager->flush();
                    
                    // Перенаправляем на страницу входа с сообщением об успехе
                    $this->addFlash('success', 'Registration successful! Please log in.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('registration/register.html.twig', [
            'error' => $error,
        ]);
    }
}