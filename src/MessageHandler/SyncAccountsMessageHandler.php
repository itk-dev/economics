<?php

namespace App\MessageHandler;

use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Message\SyncAccountsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SyncAccountsMessageHandler
{
    public function __construct(
        private DataSynchronizationService   $dataSynchronizationService,
        private DataProviderRepository       $dataProviderRepository,
        private SynchronizationJobRepository $synchronizationJobRepository,
        private LoggerInterface              $logger,
    )
    {
    }

    public function __invoke(SyncAccountsMessage $message): void
    {
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
                $this->dataSynchronizationService->syncAccounts(
                    function ($i, $length) {
                    },
                    $dataProvider
                );
            } catch (\Exception $e) {
                $job = $this->synchronizationJobRepository->find($message->getJobId());
                $job->setStatus(SynchronizationStatusEnum::ERROR);
                $job->setEnded(new \DateTime());
                $job->setMessages($job->getMessages() . ' ' . $e->getMessage());
                $this->synchronizationJobRepository->save($job, true);
                throw $e;
            }

            $job = $this->synchronizationJobRepository->find($message->getJobId());
            $job->setStatus(SynchronizationStatusEnum::DONE);
            $job->setEnded(new \DateTime());
            $this->synchronizationJobRepository->save($job, true);

        } catch (\Exception $e) {
            $this->logger->error('Failed to sync accounts', [
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
