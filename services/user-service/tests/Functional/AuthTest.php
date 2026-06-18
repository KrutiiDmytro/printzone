<?php

namespace App\Tests\Functional;

class AuthTest extends ApiTestCase
{
    public function testRegisterCreatesUser(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'newbie@example.com',
            'password' => 'secret123',
            'fullName' => 'New Bie',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->json();
        self::assertSame('newbie@example.com', $data['email']);
        self::assertContains('ROLE_USER', $data['roles']);
        self::assertNotEmpty($data['id']);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testRegisterRejectsWeakPassword(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'weak@example.com',
            'password' => 'short',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterRejectsInvalidEmail(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginReturnsJwt(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->json()['token']);
    }

    public function testLoginRejectsWrongPassword(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'WRONG',
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
