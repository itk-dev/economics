<?php

namespace App\MessageHandler;

use App\Enum\SynchronizationStatusEnum;
use App\Exception\EconomicsException;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncProjectWorklogsMessageHandler
{
    public function __construct(
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProjectWorklogsMessage $message): void
    {
        $ramBefore = memory_get_usage(true) / 1024 / 1024;
        $this->logger->info('RAM usage before execution: '.round($ramBefore, 2).' MB');

        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            return;
        }

        try {
            // Get the existing job first
            $job = $this->synchronizationJobRepository->find($message->getJobId());

            if (!$job) {
                throw new \Exception('Job not found');
            }

            $job->setStatus(SynchronizationStatusEnum::RUNNING);
            $job->setStarted(new \DateTime());
            $this->synchronizationJobRepository->save($job, true);
            try {
                $this->dataSynchronizationService->syncWorklogsForProject(
                    $message->getProjectId(),
                    $dataProvider
                );
            } catch (EconomicsException $e) {
                $job = $this->synchronizationJobRepository->find($message->getJobId());
                if (!$job) {
                    throw new \Exception('Job not found');
                }
                $job->setStatus(SynchronizationStatusEnum::ERROR);
                $job->setEnded(new \DateTime());
                $job->setMessages($job->getMessages().' '.$e->getMessage());
                $this->synchronizationJobRepository->save($job, true);
                throw $e;
            }
            $job = $this->synchronizationJobRepository->find($message->getJobId());
            if (!$job) {
                throw new \Exception('Job not found');
            }
            $job->setStatus(SynchronizationStatusEnum::DONE);
            $job->setEnded(new \DateTime());
            $this->synchronizationJobRepository->save($job, true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync worklogs for project', [
                'projectId' => $message->getProjectId(),
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $ramAfter = memory_get_usage(true) / 1024 / 1024;
        $this->logger->info('RAM usage after execution: '.round($ramAfter, 2).' MB');
    }
}
