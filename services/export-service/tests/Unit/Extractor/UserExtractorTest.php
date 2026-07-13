<?php

namespace App\Tests\Unit\Extractor;

use App\Client\UserClient;
use App\Extractor\UserExtractor;
use PHPUnit\Framework\TestCase;

class UserExtractorTest extends TestCase
{
    private function extractorReturning(array $users): UserExtractor
    {
        $client = $this->createMock(UserClient::class);
        $client->method('fetchAll')->willReturn($users);

        return new UserExtractor($client);
    }

    public function testMapsRowsAndJoinsRoles(): void
    {
        $rows = $this->extractorReturning([
            ['id' => 'u1', 'email' => 'a@b.c', 'fullName' => 'Ada', 'roles' => ['ROLE_USER', 'ROLE_ADMIN']],
        ])->extract([]);

        self::assertSame([
            ['id' => 'u1', 'email' => 'a@b.c', 'full_name' => 'Ada', 'roles' => 'ROLE_USER, ROLE_ADMIN'],
        ], $rows);
    }

    public function testFiltersByEmailSubstring(): void
    {
        $rows = $this->extractorReturning([
            ['id' => 'u1', 'email' => 'ada@shop.com', 'fullName' => 'Ada', 'roles' => ['ROLE_USER']],
            ['id' => 'u2', 'email' => 'bob@mail.com', 'fullName' => 'Bob', 'roles' => ['ROLE_USER']],
        ])->extract(['email' => 'shop']);

        self::assertCount(1, $rows);
        self::assertSame('ada@shop.com', $rows[0]['email']);
    }

    public function testFiltersByRole(): void
    {
        $rows = $this->extractorReturning([
            ['id' => 'u1', 'email' => 'a@b.c', 'fullName' => 'Ada', 'roles' => ['ROLE_USER', 'ROLE_ADMIN']],
            ['id' => 'u2', 'email' => 'b@b.c', 'fullName' => 'Bob', 'roles' => ['ROLE_USER']],
        ])->extract(['role' => 'ADMIN']);

        self::assertCount(1, $rows);
        self::assertSame('u1', $rows[0]['id']);
    }
}
