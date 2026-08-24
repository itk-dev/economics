<?php

namespace App\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\LeantimeDeleteMessage;
use App\MessageHandler\Trait\RethrowsTransientHttpFailuresTrait;
use App\Service\LeantimeApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[AsMessageHandler]
readonly class LeantimeDeleteHandler
{
    use RethrowsTransientHttpFailuresTrait;

    public function __construct(
        private LoggerInterface $logger,
        private LeantimeApiService $leantimeApiService,
    ) {
    }

    public function __invoke(LeantimeDeleteMessage $message): void
    {
        try {
            $this->logger->info(sprintf(
                'Handling delete message. type: %s, start: %d, deletedAfter: %s',
                $message->type,
                $message->start,
                $message->deletedAfter?->format('c') ?? 'none',
            ));

            $this->leantimeApiService->deleteAsJob(
                $message->type,
                $message->start,
                $message->limit,
                $message->dataProviderId,
                $message->asyncJobQueue,
                $message->deletedAfter,
            );
        } catch (ClientExceptionInterface $e) {
            $this->rethrowUnlessPermanent($e, $this->logger);
        } catch (NotFoundException|\TypeError $e) {
            // Narrow on purpose: see UpsertIssueHandler. Infrastructure failures must propagate.
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
