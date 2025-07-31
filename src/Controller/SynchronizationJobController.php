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
    public function __construct()
    {
    }

    #[Route('/status', name: 'app_synchronization_status', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function status(SynchronizationJobRepository $synchronizationJobRepository, TranslatorInterface $translator): Response
    {
        $jobQueueLength = $synchronizationJobRepository->countQueuedJobs();

        $nextJob = $synchronizationJobRepository->getNextJob();

        $failedJobs = $synchronizationJobRepository->countFailedJobs();
        $currentJob = $synchronizationJobRepository->getCurrentJob();
        $elapsedSeconds = null;
        $elapsed = null;
        if (null !== $currentJob) {
            $started = $currentJob->getStarted();
            if (null !== $started) {
                $elapsedSeconds = ((new \DateTime('now'))->getTimestamp() - $started->getTimestamp());
                $elapsed = (new \DateTime('now'))->diff($started)->format('%H:%I:%S');
            }
        }
        if (null === $nextJob) {
            return new JsonResponse([], 200);
        }

        return new JsonResponse([
            'queueLength' => $jobQueueLength,
            'status' => $nextJob->getStatus()?->value,
            'ended' => $nextJob->getEnded()?->format('c'),
            'elapsed' => $elapsedSeconds > 20 ? $elapsed : null,
            'errors' => $failedJobs,
        ]);
    }

    #[Route('/start', name: 'app_synchronization_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sync(SyncService $syncService): Response
    {
        if (!$syncService->canStartNewSync()) {
            return new JsonResponse(['message' => 'existing job'], Response::HTTP_CONFLICT);
        }

        $job = $syncService->createJob();
        if (null === $job) {
            throw new \Exception('Job id not found');
        }

        $syncService->sync();

        return new JsonResponse([], Response::HTTP_OK);
    }
}
