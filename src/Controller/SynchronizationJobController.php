<?php

namespace App\Controller;

use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Message\SynchronizeMessage;
use App\Repository\SynchronizationJobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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

        if (null === $nextJob) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'queueLength' => $jobQueueLength,
            'status' => $nextJob->getStatus()?->value,
            'ended' => $nextJob->getEnded()?->format('c'), ]);

        /*        return new JsonResponse([
                    'started' => $latestJob->getStarted()?->format('c'),
                    'ended' => $latestJob->getEnded()?->format('c'),
                    'status' => $latestJob->getStatus()?->value,
                    'step' => $latestJob->getStep()?->trans($translator) ?? $latestJob->getStep()?->value,
                    'progress' => $latestJob->getProgress(),
                ], 200);*/
    }

    #[Route('/start', name: 'app_synchronization_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sync(SynchronizationJobRepository $synchronizationJobRepository, MessageBusInterface $bus): Response
    {
        $latestJob = $synchronizationJobRepository->getLatestJob();

        if (null !== $latestJob && in_array($latestJob->getStatus(), [SynchronizationStatusEnum::NOT_STARTED, SynchronizationStatusEnum::RUNNING])) {
            return new JsonResponse(['message' => 'existing job'], Response::HTTP_CONFLICT);
        }

        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $synchronizationJobRepository->save($job, true);

        $jobId = $job->getId();

        if (null === $jobId) {
            throw new \Exception('Job id not found');
        }

        $message = new SynchronizeMessage($jobId);

        $bus->dispatch($message);

        return new JsonResponse([], 200);
    }
}
