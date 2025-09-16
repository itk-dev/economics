<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SyncProjectWorklogsMessageHandler
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
    public function __invoke(SyncProjectWorklogsMessage $message): void
    {
        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            throw new UnrecoverableMessageHandlingException('Data provider not found', 404);
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
            throw new UnrecoverableMessageHandlingException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
