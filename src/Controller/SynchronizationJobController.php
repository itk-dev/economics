<?php

namespace App\Controller;

use App\Service\DataProviderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/synchronization')]
class SynchronizationJobController extends AbstractController
{
    public function __construct(
        private readonly DataProviderService $dataProviderService,
    ) {
    }

    #[Route('/status', name: 'app_synchronization_status', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function status(): Response
    {
        $asyncLength = $this->dataProviderService->countPendingJobsByQueueName('async');
        $failedLength = $this->dataProviderService->countFailedJobsTheLastDay();

        return new JsonResponse([
            'async' => $asyncLength,
            'error' => $failedLength,
        ]);
    }
}
