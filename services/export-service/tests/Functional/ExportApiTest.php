<?php

namespace App\Tests\Functional;

use App\Entity\ExportJob;

class ExportApiTest extends ApiTestCase
{
    public function testCreateRequiresToken(): void
    {
        $this->send('POST', '/api/exports', ['type' => 'products', 'format' => 'csv', 'requestedBy' => 'a@b.c']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateForbiddenWithoutRole(): void
    {
        $this->send('POST', '/api/exports', ['type' => 'products', 'format' => 'csv', 'requestedBy' => 'a@b.c'], $this->serviceToken());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateReturnsPendingJobAndPersists(): void
    {
        $this->send('POST', '/api/exports', [
            'type' => 'products',
            'format' => 'csv',
            'requestedBy' => 'admin@example.com',
            'filters' => ['category' => 'mugs'],
        ], $this->exportAdminToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('pending', $body['status']);
        self::assertSame('products', $body['type']);
        self::assertNotEmpty($body['id']);

        $this->em->clear();
        $job = $this->em->getRepository(ExportJob::class)->find($body['id']);
        self::assertInstanceOf(ExportJob::class, $job);
        self::assertSame('admin@example.com', $job->getRequestedBy());
    }

    public function testCreateRejectsInvalidType(): void
    {
        $this->send('POST', '/api/exports', ['type' => 'nope', 'format' => 'csv', 'requestedBy' => 'a@b.c'], $this->exportAdminToken());

        self::assertResponseStatusCodeSame(400);
    }

    public function testGetUnknownReturns404(): void
    {
        $this->send('GET', '/api/exports/not-a-uuid', [], $this->exportAdminToken());

        self::assertResponseStatusCodeSame(404);
    }
}
