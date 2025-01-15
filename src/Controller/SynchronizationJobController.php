<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Form\ProjectFilterType;
use App\Form\ProjectType;
use App\Message\SynchronizeMessage;
use App\Model\Invoices\ProjectFilterData;
use App\Repository\ProjectRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/synchronization')]
#[IsGranted('ROLE_ADMIN')]
class SynchronizationJobController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('/status', name: 'app_synchronization_status', methods: ['GET'])]
    public function status(SynchronizationJobRepository $synchronizationJobRepository): Response
    {
        $latestJob = $synchronizationJobRepository->getLatestJob();

        return new JsonResponse([
            'started' => $latestJob->getStarted()?->format('c'),
            'ended' => $latestJob->getEnded()?->format('c'),
            'status' => $latestJob->getStatus()?->name,
            'step' => $latestJob->getStep()?->name,
            'progress' => $latestJob->getProgress(),
            'messages' => $latestJob->getMessages(),
        ], 200);
    }

    #[Route('/start', name: 'app_synchronization_sync', methods: ['POST'])]
    public function sync(SynchronizationJobRepository $synchronizationJobRepository, MessageBusInterface $bus): Response
    {
        if ($synchronizationJobRepository->getIsRunning()) {
            return new JsonResponse(['message' => 'already running'], Response::HTTP_CONFLICT);
        }

        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $synchronizationJobRepository->save($job, true);

        $bus->dispatch(new SynchronizeMessage($job->getId()));

        return new JsonResponse([], 200);
    }
}
