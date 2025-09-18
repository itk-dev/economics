<?php

namespace App\MessageHandler;

use App\Message\SyncProjectIssuesMessage;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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
     * @throws UnrecoverableMessageHandlingException
     */
    public function __invoke(SyncProjectIssuesMessage $message): void
    {
        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            throw new UnrecoverableMessageHandlingException('Data provider not found', 404);
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
            throw new UnrecoverableMessageHandlingException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
