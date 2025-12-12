<?php

namespace App\MessageHandler;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Exception\NotSupportedException;
use App\Message\EntityRemovedFromDataProviderMessage;
use App\Service\DataProviderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class EntityRemovedFromDataProviderHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private DataProviderService $dataProviderService,
    ) {
    }

    public function __invoke(EntityRemovedFromDataProviderMessage $message): void
    {
        try {
            $this->logger->info('EntityRemovedFromSourceHandler: '.$message->classname.' '.$message->projectTrackerId);

            match ($message->classname) {
                Project::class => $this->dataProviderService->projectRemovedFromDataProvider($message->dataProviderId, $message->projectTrackerId, $message->deletedDate),
                Version::class => $this->dataProviderService->versionRemovedFromDataProvider($message->dataProviderId, $message->projectTrackerId),
                Issue::class => $this->dataProviderService->issueRemovedFromDataProvider($message->dataProviderId, $message->projectTrackerId, $message->deletedDate),
                Worklog::class => $this->dataProviderService->worklogRemovedFromDataProvider($message->dataProviderId, (int) $message->projectTrackerId, $message->deletedDate),
                default => throw new NotSupportedException('classname not supported'),
            };
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new UnrecoverableMessageHandlingException($e->getMessage());
        }
    }
}
