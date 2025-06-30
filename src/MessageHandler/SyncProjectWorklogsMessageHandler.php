<?php

namespace App\MessageHandler;

use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncProjectWorklogsMessageHandler
{
    public function __construct(
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProjectWorklogsMessage $message): void
    {
        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            return;
        }

        try {
            $this->dataSynchronizationService->syncWorklogsForProject(
                $message->getProjectId(),
                $dataProvider
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync worklogs for project', [
                'projectId' => $message->getProjectId(),
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
