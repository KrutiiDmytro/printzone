<?php

namespace App\Tests\Functional;

class UserApiTest extends ApiTestCase
{
    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testListAllowedForAdmin(): void
    {
        $token = $this->login('admin@example.com', 'admin123');
        $this->jsonRequest('GET', '/api/users', token: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->json());
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $token = $this->login('user@example.com', 'user1234');
        $this->jsonRequest('GET', '/api/users', token: $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCanReadOwnProfile(): void
    {
        $token = $this->login('user@example.com', 'user1234');
        $this->jsonRequest('GET', '/api/users/'.$this->userId('user@example.com'), token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('user@example.com', $this->json()['email']);
    }

    public function testUserCannotReadOthersProfile(): void
    {
        $token = $this->login('user@example.com', 'user1234');
        $this->jsonRequest('GET', '/api/users/'.$this->userId('admin@example.com'), token: $token);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanReadAnyProfile(): void
    {
        $token = $this->login('admin@example.com', 'admin123');
        $this->jsonRequest('GET', '/api/users/'.$this->userId('user@example.com'), token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('user@example.com', $this->json()['email']);
    }
}
