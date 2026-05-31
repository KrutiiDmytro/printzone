<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use App\Export\Service\ExportService;
use App\Repository\ExportJobRepository;
use App\Storage\FileStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ExportController extends AbstractController
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly ExportJobRepository $exportJobRepository,
        private readonly FileStorageInterface $storage,
    ) {}

    #[Route('/admin/export', name: 'admin_export', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/export/index.html.twig', [
            'jobs' => $this->exportJobRepository->findRecent(),
            'export_types' => ExportType::cases(),
            'export_formats' => ExportFormat::cases(),
            'order_statuses' => ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'],
        ]);
    }

    #[Route('/admin/export', name: 'admin_export_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('export', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Невірний CSRF-токен. Спробуйте ще раз.');
            return $this->redirectToRoute('admin_export');
        }

        $typeValue = $request->request->getString('type');
        $formatValue = $request->request->getString('format');

        $type = ExportType::tryFrom($typeValue);
        $format = ExportFormat::tryFrom($formatValue);

        if (null === $type || null === $format) {
            $this->addFlash('danger', 'Невірний тип або формат експорту.');
            return $this->redirectToRoute('admin_export');
        }

        $filters = array_filter($request->request->all('filters') ?? []);

        /** @var \App\User\Domain\Entity\User $user */
        $user = $this->getUser();
        $job = $this->exportService->dispatch($type, $format, $user->getEmail(), $filters);

        $this->addFlash('success', sprintf(
            'Завдання #%d на експорт %s (%s) поставлено в чергу.',
            $job->getId(),
            $type->value,
            $format->value
        ));

        return $this->redirectToRoute('admin_export');
    }

    #[Route('/admin/export/download/{id}', name: 'admin_export_download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $job = $this->exportJobRepository->find($id);
        if (null === $job || null === $job->getFilePath()) {
            throw $this->createNotFoundException('Файл не знайдено.');
        }

        /** @var \App\User\Domain\Entity\User $user */
        $user = $this->getUser();
        if ($job->getRequestedBy() !== $user->getEmail()) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $job->getFilePath();
        $content = $this->storage->read($filePath);
        $filename = basename($filePath);
        $contentType = $job->getFormat()->value === 'csv' ? 'text/csv'
            : ($job->getFormat()->value === 'json' ? 'application/json' : 'application/xml');

        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
