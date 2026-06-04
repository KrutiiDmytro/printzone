<?php

namespace App\Controller;

use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use League\OAuth2\Client\Provider\Github;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class GitHubAuthController extends AbstractController
{
    private Github $provider;

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
        private string $githubClientId,
        private string $githubClientSecret,
        private string $githubRedirectUri,
    ) {
        $this->provider = new Github([
            'clientId'     => $this->githubClientId,
            'clientSecret' => $this->githubClientSecret,
            'redirectUri'  => $this->githubRedirectUri,
        ], [
            'httpClient' => new GuzzleClient(['timeout' => 10, 'connect_timeout' => 5]),
        ]);
    }

    #[Route('/auth/github', name: 'auth_github')]
    public function redirectToGithub(): RedirectResponse
    {
        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['user:email'],
        ]);

        return $this->redirect($authUrl);
    }

    #[Route('/auth/github/callback', name: 'auth_github_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');

        if (!$code) {
            $this->addFlash('error', 'GitHub authentication failed');
            return $this->redirectToRoute('app_login');
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $githubUser = $this->provider->getResourceOwner($token);

            $email = $githubUser->getEmail();
            $githubId = (string) $githubUser->getId();

            if (!$email) {
                $this->addFlash('error', 'GitHub account has no public email');
                return $this->redirectToRoute('app_login');
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setFullName($githubUser->getName() ?? $githubUser->getNickname());
                $user->setPassword(
                    $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16)))
                );
                $this->em->persist($user);
            }

            $this->em->flush();

            $this->security->login($user);

            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $this->addFlash('error', 'GitHub authentication error: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
}