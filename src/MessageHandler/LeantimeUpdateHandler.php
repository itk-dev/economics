<?php

namespace App\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\LeantimeUpdateMessage;
use App\Service\LeantimeApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class LeantimeUpdateHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService,
    ) {
    }

    public function __invoke(LeantimeUpdateMessage $message): void
    {
        try {
            $this->logger->info("Handling $message->className update message. start: $message->start, limit: $message->limit");

            $this->leantimeApiService->updateAsJob(
                $message->className,
                $message->start,
                $message->limit,
                $message->dataProviderId,
                $message->projectTrackerProjectIds,
                $message->asyncJobQueue,
                $message->modifiedAfter,
                $message->disableModifiedAtCheck,
            );
        } catch (NotFoundException|\TypeError $e) {
            // Narrow on purpose: see UpsertIssueHandler. A page that fails because Leantime or the
            // database is unavailable must be retried, not dropped along with the pages after it.
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
