<?php

namespace App\Controller;

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
        private readonly SyncService $syncService,
    ) {
    }

    #[Route('/status', name: 'app_synchronization_status', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function status(): Response
    {
        $queueLength = $this->syncService->countPendingJobsByQueueName('async');
        $failedJobs = $this->syncService->countPendingJobsByQueueName('failed');

        return new JsonResponse([
            'queueLength' => $queueLength,
            'errors' => $failedJobs,
        ]);
    }
}
