<?php

namespace App\MessageHandler;

use App\Exception\EconomicsException;
use App\Message\LeantimeUpdateMessage;
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
readonly class LeantimeUpdateHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService,
    ) {}

    public function __invoke(LeantimeUpdateMessage $message): void
    {
        try {
            $this->logger->info("Handling $message->type update message. start: $message->start, limit: $message->limit");

            match ($message->type) {
                LeantimeApiService::PROJECTS => $this->leantimeApiService->updateProjectsAsJob(
                    $message->start,
                    $message->limit,
                    $message->dataProvider
                ),
                LeantimeApiService::MILESTONES => $this->leantimeApiService->updateVersionsAsJob(
                    $message->start,
                    $message->limit,
                    $message->dataProvider,
                    $message->projectTrackerProjectIds,
                ),
                LeantimeApiService::TICKETS => $this->leantimeApiService->updateIssuesAsJob(
                    $message->start,
                    $message->limit,
                    $message->dataProvider,
                    $message->projectTrackerProjectIds,
                ),
                LeantimeApiService::TIMESHEETS => $this->leantimeApiService->updateWorklogsAsJob(
                    $message->start,
                    $message->limit,
                    $message->dataProvider,
                    $message->projectTrackerProjectIds,
                )
            };
        } catch (\Exception $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
