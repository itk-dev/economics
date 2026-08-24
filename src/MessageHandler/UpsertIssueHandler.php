<?php

namespace App\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\UpsertIssueMessage;
use App\Service\DataProviderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertIssueHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProviderService $dataProviderService,
    ) {
    }

    public function __invoke(UpsertIssueMessage $message): void
    {
        try {
            $this->logger->info('Upserting issue: '.$message->issueData->name);
            $this->dataProviderService->upsertIssue($message->issueData);
        } catch (NotFoundException|\TypeError $e) {
            // Narrow on purpose: only a failure describing this one row is unrecoverable. TypeError
            // is here because a null source field mapped onto a non-nullable property raises an
            // Error, not an Exception. Anything else propagates, so it is retried, not dropped.
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
