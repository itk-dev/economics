<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\UpdateProjectBillingMessage;
use App\Message\UpsertProjectMessage;
use App\Service\LeantimeApiService;
use App\Service\ProjectBillingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertProjectHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService
    ) {}

    public function __invoke(UpsertProjectMessage $message): void
    {
        try {
            $this->logger->info("upserting project: ".$message->projectData->name);
            $this->leantimeApiService->upsertProject($message->projectData);
        } catch (\Exception $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
