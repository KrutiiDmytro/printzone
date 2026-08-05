<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    public function list(UserRepository $users): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Admin role required.');
        }

        $data = array_map($this->serialize(...), $users->findAll());

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'users_get', methods: ['GET'])]
    public function get(string $id, UserRepository $users): JsonResponse
    {
        $user = $users->findOneBy(['id' => $id]);
        if (null === $user) {
            throw new NotFoundHttpException('User not found.');
        }

        $current = $this->security->getUser();
        $isSelf = $current instanceof User && $current->getId()->equals($user->getId());
        if (!$isSelf && !$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('You can only access your own profile.');
        }

        return new JsonResponse($this->serialize($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
        ];
    }
}
