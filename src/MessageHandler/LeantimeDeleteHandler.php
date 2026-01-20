<?php

namespace App\MessageHandler;

use App\Message\LeantimeDeleteMessage;
use App\Service\LeantimeApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class LeantimeDeleteHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService,
    ) {
    }

    public function __invoke(LeantimeDeleteMessage $message): void
    {
        try {
            $this->logger->info('Handling delete message. deletedAfter: '.$message->deletedAfter?->format('c'));

            $this->leantimeApiService->deleteAsJob(
                $message->dataProviderId,
                $message->asyncJobQueue,
                $message->deletedAfter,
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
