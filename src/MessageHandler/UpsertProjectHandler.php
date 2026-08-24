<?php

namespace App\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\UpsertProjectMessage;
use App\Service\DataProviderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertProjectHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProviderService $dataProviderService,
    ) {
    }

    public function __invoke(UpsertProjectMessage $message): void
    {
        try {
            $this->logger->info('Upserting project: '.$message->projectData->name);
            $this->dataProviderService->upsertProject($message->projectData);
        } catch (NotFoundException|\TypeError $e) {
            // Narrow on purpose: see UpsertIssueHandler. Infrastructure failures must propagate.
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
