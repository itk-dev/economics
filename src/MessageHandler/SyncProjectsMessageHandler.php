<?php

namespace App\MessageHandler;

use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncProjectsMessage;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SyncProjectsMessageHandler
{
    public function __construct(
        private DataSynchronizationService $dataSynchronizationService,
        private DataProviderRepository $dataProviderRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UnsupportedDataProviderException
     */
    public function __invoke(SyncProjectsMessage $message): void
    {
        $dataProvider = $this->dataProviderRepository->find($message->getDataProviderId());

        if (!$dataProvider) {
            $this->logger->error('Data provider not found', [
                'dataProviderId' => $message->getDataProviderId(),
            ]);

            throw new UnrecoverableMessageHandlingException('Data provider not found', 404);
        }

        try {
            $this->dataSynchronizationService->syncProjects(
                function ($i, $length) {
                },
                $dataProvider
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync projects', [
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
