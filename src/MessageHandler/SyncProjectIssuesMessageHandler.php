<?php

namespace App\MessageHandler;

use App\Enum\SynchronizationStatusEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncProjectIssuesMessage;
use App\Repository\DataProviderRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SyncProjectIssuesMessageHandler
{
    public function __construct(
        private DataSynchronizationService $dataSynchronizationService,
        private DataProviderRepository $dataProviderRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    public function __invoke(SyncProjectIssuesMessage $message): void
    {
        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            return;
        }
        try {
            $this->dataSynchronizationService->syncIssuesForProject(
                $message->getProjectId(),
                $dataProvider
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync issues for project', [
                'projectId' => $message->getProjectId(),
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }


    }
}
