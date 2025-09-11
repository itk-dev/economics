<?php

namespace App\MessageHandler;

use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncAccountsMessage;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SyncAccountsMessageHandler
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
            $this->dataSynchronizationService->syncAccounts(
                function ($i, $length) {
                },
                $dataProvider
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync issues for project', [
                'dataProviderId' => $message->getDataProviderId(),
                'error' => $e->getMessage(),
            ]);
            throw new UnrecoverableMessageHandlingException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
