<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Export\Client\ExportClient;
use App\Export\Enum\ExportFormat;
use App\Export\Enum\ExportType;
use App\Export\ViewModel\ExportJobView;
use App\Storage\FileStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_ADMIN')]
final class ExportController extends AbstractController
{
    public function __construct(
        private readonly ExportClient $exportClient,
        private readonly FileStorageInterface $storage,
    ) {
    }

    #[Route('/admin/export', name: 'admin_export', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/export/index.html.twig', [
            'jobs' => array_map(ExportJobView::fromArray(...), $this->exportClient->listRecent()),
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

        $type = ExportType::tryFrom($request->request->getString('type'));
        $format = ExportFormat::tryFrom($request->request->getString('format'));

        if (null === $type || null === $format) {
            $this->addFlash('danger', 'Невірний тип або формат експорту.');

            return $this->redirectToRoute('admin_export');
        }

        $filters = array_filter($request->request->all('filters') ?? []);

        /** @var \App\User\Domain\Entity\User $user */
        $user = $this->getUser();

        try {
            $job = $this->exportClient->create($type, $format, $user->getEmail(), $filters);
            $this->addFlash('success', sprintf(
                'Завдання #%s на експорт %s (%s) поставлено в чергу.',
                $job['id'] ?? '?',
                $type->value,
                $format->value
            ));
        } catch (\Throwable) {
            $this->addFlash('danger', 'Не вдалося поставити завдання в чергу. Спробуйте пізніше.');
        }

        return $this->redirectToRoute('admin_export');
    }

    #[Route('/admin/export/download/{id}', name: 'admin_export_download', methods: ['GET'])]
    public function download(string $id): Response
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Файл не знайдено.');
        }
        $job = $this->exportClient->get($id);
        if (null === $job || empty($job['filePath'])) {
            throw $this->createNotFoundException('Файл не знайдено.');
        }

        /** @var \App\User\Domain\Entity\User $user */
        $user = $this->getUser();
        if (($job['requestedBy'] ?? null) !== $user->getEmail()) {
            throw $this->createAccessDeniedException();
        }

        $filePath = (string) $job['filePath'];
        $content = $this->storage->read($filePath);
        $filename = basename($filePath);
        $format = (string) ($job['format'] ?? '');
        $contentType = 'csv' === $format ? 'text/csv'
            : ('json' === $format ? 'application/json' : 'application/xml');

        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
