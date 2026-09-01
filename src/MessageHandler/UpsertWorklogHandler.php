<?php

namespace App\MessageHandler;

use App\Exception\NotAcceptableException;
use App\Exception\NotFoundException;
use App\Message\UpsertWorklogMessage;
use App\Service\DataProviderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class UpsertWorklogHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProviderService $dataProviderService,
    ) {
    }

    public function __invoke(UpsertWorklogMessage $message): void
    {
        try {
            $this->logger->info('Upserting worklog: '.$message->worklogData->projectTrackerId);
            $this->dataProviderService->upsertWorklog($message->worklogData);
        } catch (NotFoundException|NotAcceptableException|\TypeError $e) {
            // Narrow on purpose: see UpsertIssueHandler. Infrastructure failures must propagate.
            // NotAcceptableException belongs here for the same reason as the other two: it describes
            // one unusable row, so marking it unrecoverable is what lets the sync transport skip it
            // and carry on rather than abandon the pages behind it.
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
