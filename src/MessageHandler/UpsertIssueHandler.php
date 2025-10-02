<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\UpdateProjectBillingMessage;
use App\Message\UpsertIssueMessage;
use App\Message\UpsertProjectMessage;
use App\Message\UpsertVersionMessage;
use App\Service\DataProviderService;
use App\Service\LeantimeApiService;
use App\Service\ProjectBillingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertIssueHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProviderService $dataProviderService
    ) {}

    public function __invoke(UpsertIssueMessage $message): void
    {
        try {
            $this->logger->info("Upserting issue: ".$message->issueData->name);
            $this->dataProviderService->upsertIssue($message->issueData);
        } catch (\Exception $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
