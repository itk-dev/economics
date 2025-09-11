<?php

namespace App\MessageHandler;

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
        private DataProviderRepository $dataProviderRepository,
        private LoggerInterface $logger,
    ) {
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

    }
}
