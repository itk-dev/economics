<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\UpdateProjectBillingMessage;
use App\Message\UpsertProjectMessage;
use App\Message\UpsertVersionMessage;
use App\Service\LeantimeApiService;
use App\Service\ProjectBillingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertVersionHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService
    ) {}

    public function __invoke(UpsertVersionMessage $message): void
    {
        try {
            $this->logger->info("upserting version: ".$message->versionData->name);
            $this->leantimeApiService->upsertVersion($message->versionData);
        } catch (\Exception $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
