<?php

namespace App\Tests\Unit\Controller;

use App\Controller\GitHubAuthController;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GitHubAuthControllerTest extends TestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
    }

    public function testCallbackWithoutCodeRedirectsToLogin(): void
    {
        $controller = new GitHubAuthController($this->em, $this->passwordHasher);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->callback($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testCallbackWithInvalidCodeRedirectsToLogin(): void
    {
        $controller = new GitHubAuthController($this->em, $this->passwordHasher);

        $request = new Request(['code' => 'invalid_code']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $controller->callback($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }
}
