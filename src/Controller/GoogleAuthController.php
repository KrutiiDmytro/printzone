<?php

namespace App\Controller;

use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use League\OAuth2\Client\Provider\Google;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


class GoogleAuthController extends AbstractController
{
    private Google $provider;

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private string $googleClientId,
        private string $googleClientSecret,
        private string $googleRedirectUri,
    ) {
        $this->provider = new Google([
            'clientId'     => $this->googleClientId,
            'clientSecret' => $this->googleClientSecret,
            'redirectUri'  => $this->googleRedirectUri,
        ], [
            'httpClient' => new GuzzleClient(['timeout' => 10, 'connect_timeout' => 5]),
        ]);
    }

    #[Route('/auth/google', name: 'auth_google')]
    public function redirectToGoogle(): RedirectResponse
    {
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);

        return $this->redirect($authUrl);
    }

    #[Route('/auth/google/callback', name: 'auth_google_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');
        $errorDescription = $request->query->get('error_description');

        if (!$code) {
            $this->addFlash('error', 'Google auth failed: ' . ($error ?? 'no_code') . ' - ' . ($errorDescription ?? ''));
            return $this->redirectToRoute('app_login');
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $googleUser = $this->provider->getResourceOwner($token);

            $email = $googleUser->getEmail();
            $googleId = $googleUser->getId();

            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setFullName($googleUser->getName());
                $user->setGoogleId($googleId);
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16)))
                );
                $this->em->persist($user);
            } else {
                $user->setGoogleId($googleId);
            }

            $this->em->flush();

            // Авторизуем пользователя через сессию
            $securityToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $request->getSession()->set('_security_main', serialize($securityToken));
            $request->getSession()->save();
            
            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Google authentication error: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
}