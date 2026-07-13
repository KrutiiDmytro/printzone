<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExportJob;
use App\Enum\ExportFormat;
use App\Enum\ExportType;
use App\Repository\ExportJobRepository;
use App\Service\ExportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/exports')]
final class ExportController
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly ExportJobRepository $repository,
    ) {
    }

    #[Route('', name: 'exports_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent() ?: '{}', true) ?? [];

        $type = ExportType::tryFrom((string) ($data['type'] ?? ''));
        $format = ExportFormat::tryFrom((string) ($data['format'] ?? ''));
        $requestedBy = trim((string) ($data['requestedBy'] ?? ''));

        if (null === $type || null === $format) {
            return new JsonResponse(['error' => 'Invalid export type or format.'], 400);
        }
        if ('' === $requestedBy) {
            return new JsonResponse(['error' => 'requestedBy is required.'], 400);
        }

        $filters = is_array($data['filters'] ?? null) ? array_filter($data['filters']) : [];

        $job = $this->exportService->dispatch($type, $format, $requestedBy, $filters);

        return new JsonResponse($this->serialize($job), 201);
    }

    #[Route('', name: 'exports_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['data' => array_map($this->serialize(...), $this->repository->findRecent())]);
    }

    #[Route('/{id}', name: 'exports_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Export not found.'], 404);
        }
        $job = $this->repository->find(Uuid::fromString($id));
        if (null === $job) {
            return new JsonResponse(['error' => 'Export not found.'], 404);
        }

        return new JsonResponse($this->serialize($job));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ExportJob $job): array
    {
        return [
            'id' => (string) $job->getId(),
            'type' => $job->getType()->value,
            'format' => $job->getFormat()->value,
            'status' => $job->getStatus()->value,
            'filePath' => $job->getFilePath(),
            'filters' => $job->getFilters(),
            'requestedBy' => $job->getRequestedBy(),
            'createdAt' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $job->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'errorMessage' => $job->getErrorMessage(),
        ];
    }
}
