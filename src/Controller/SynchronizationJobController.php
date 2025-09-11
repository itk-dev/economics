<?php

namespace App\Controller;

use App\Repository\SynchronizationJobRepository;
use App\Service\SyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/synchronization')]
class SynchronizationJobController extends AbstractController
{
    public function __construct(
        private readonly SyncService $syncService
    )
    {
    }

    #[Route('/status', name: 'app_synchronization_status', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function status(TranslatorInterface $translator): Response
    {
        $queueLength = $this->syncService->countPendingJobsByQueueName('async');
        $failedJobs = $this->syncService->countPendingJobsByQueueName('failed');

        return new JsonResponse([
            'status' => 'DONE',
            'queueLength' => $queueLength,
            'errors' => $failedJobs
        ]);
    }

    #[Route('/start', name: 'app_synchronization_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sync(SyncService $syncService): Response
    {
        $queueLength = $this->syncService->countPendingJobsByQueueName('async');

        if (0 !== $queueLength)
        {
            return new JsonResponse(['message' => 'Queue not empty'], Response::HTTP_CONFLICT);
        }

        $syncService->sync();

        return new JsonResponse([], Response::HTTP_OK);
    }
}
