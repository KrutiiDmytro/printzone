<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController
{
    /**
     * Stub route so the router resolves /api/auth/login (RouterListener runs
     * before the firewall). The json_login authenticator intercepts the request
     * and returns the JWT, so this method body is never executed.
     */
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Handled by the json_login firewall.');
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent() ?: '{}', true) ?? [];

        $email = is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $fullName = is_string($data['fullName'] ?? null) ? $data['fullName'] : null;

        if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'A valid email is required.'], 422);
        }
        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'Password must be at least 8 characters.'], 422);
        }
        if (null !== $users->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Email already registered.'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
        ], 201);
    }
}
