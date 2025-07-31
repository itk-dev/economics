<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SynchronizationJobRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function sync(?SymfonyStyle $io = null): void
    {
        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);
        $this->syncDataProviderBasedItems($dataProviders, $io);
        $this->syncProjectBasedItems($dataProviders, $io);
    }

    public function canStartNewSync(): bool
    {
        $latestJob = $this->synchronizationJobRepository->getLatestJob();

        return null === $latestJob || !in_array($latestJob->getStatus(), [
            SynchronizationStatusEnum::NOT_STARTED,
            SynchronizationStatusEnum::RUNNING,
        ]);
    }

    public function createInitialJob(): ?SynchronizationJob
    {
        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $this->synchronizationJobRepository->save($job, true);

        return $job;
    }

    private function syncDataProviderBasedItems(array $dataProviders, ?SymfonyStyle $io): void
    {
        // Sync projects
        $this->dispatchJobs(
            $dataProviders,
            'Projects',
            fn ($item, $dataProviderId, $jobId) => new SyncProjectsMessage($dataProviderId, $jobId),
            fn ($type, $item, $dataProviderId) => sprintf('%s - dataProviderId: %d', $type, $dataProviderId),
            fn ($item) => $item->getName(),
            $io
        );

        // Sync accounts for enabled providers
        $accountEnabledProviders = array_filter($dataProviders, fn ($dp) => $dp->isEnableAccountSync());
        $this->dispatchJobs(
            $accountEnabledProviders,
            'Accounts',
            fn ($item, $dataProviderId, $jobId) => new SyncAccountsMessage($dataProviderId, $jobId),
            fn ($type, $item, $dataProviderId) => sprintf('%s - dataProviderId: %d', $type, $dataProviderId),
            fn ($item) => $item->getName(),
            $io
        );
    }

    private function syncProjectBasedItems(array $dataProviders, ?SymfonyStyle $io): void
    {
        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            $this->dispatchJobs(
                $projects,
                'Issues',
                fn ($item, $dataProviderId, $jobId) => new SyncProjectIssuesMessage(
                    $item->getProjectTrackerId(),
                    $dataProviderId,
                    $jobId
                ),
                fn ($type, $item, $dataProviderId) => sprintf(
                    '%s - projectId: %d dataProviderId: %d',
                    $type,
                    $item->getProjectTrackerId(),
                    $dataProviderId
                ),
                fn ($item) => $item->getName(),
                $io
            );

            $this->dispatchJobs(
                $projects,
                'Worklogs',
                fn ($item, $dataProviderId, $jobId) => new SyncProjectWorklogsMessage(
                    $item->getProjectTrackerId(),
                    $dataProviderId,
                    $jobId
                ),
                fn ($type, $item, $dataProviderId) => sprintf(
                    '%s - projectId: %d dataProviderId: %d',
                    $type,
                    $item->getProjectTrackerId(),
                    $dataProviderId
                ),
                fn ($item) => $item->getName(),
                $io
            );
        }
    }

    private function dispatchJobs(
        array $items,
        string $type,
        callable $messageFactory,
        callable $messageFormatter,
        callable $getName,
        ?SymfonyStyle $io = null,
    ): void {
        $this->announceJobDispatch($type, $io);

        foreach ($items as $item) {
            $dataProviderId = $this->getDataProviderId($item);
            if (null === $dataProviderId) {
                continue;
            }

            $jobMessage = $messageFormatter($type, $item, $dataProviderId);
            $this->processJob($type, $item, $dataProviderId, $jobMessage, $messageFactory, $getName, $io);
        }
    }

    private function processJob(
        string $type,
        object $item,
        int $dataProviderId,
        string $jobMessage,
        callable $messageFactory,
        callable $getName,
        ?SymfonyStyle $io = null,
    ): void {
        $this->deleteCompletedJobs($jobMessage);

        $job = $this->createSyncJob($jobMessage);
        if (null === $job) {
            $this->logDuplicateJob($type, $getName($item), $io);

            return;
        }

        $this->dispatchSingleJob($type, $item, $dataProviderId, $job, $messageFactory, $getName, $io);
    }

    private function announceJobDispatch(string $type, ?SymfonyStyle $io): void
    {
        if ($io) {
            $io->info(sprintf('Dispatching %s sync jobs', strtolower($type)));
        }
    }

    private function getDataProviderId(object $item): ?int
    {
        return $item instanceof DataProvider
            ? $item->getId()
            : $item->getDataProvider()->getId();
    }

    private function logDuplicateJob(string $type, string $itemName, ?SymfonyStyle $io): void
    {
        if ($io) {
            $io->writeln(sprintf(
                'Duplicate %s sync job already exists for %s',
                strtolower($type),
                $itemName
            ));
        }
    }

    private function dispatchSingleJob(
        string $type,
        object $item,
        int $dataProviderId,
        SynchronizationJob $job,
        callable $messageFactory,
        callable $getName,
        ?SymfonyStyle $io = null,
    ): void {
        if ($io) {
            $io->writeln(sprintf(
                'Dispatching %s sync job for %s',
                strtolower($type),
                $getName($item)
            ));
        }

        $message = $messageFactory($item, $dataProviderId, $job->getId());
        $this->messageBus->dispatch($message);
    }

    private function deleteCompletedJobs(string $message): void
    {
        $doneJobs = $this->synchronizationJobRepository->findBy([
            'status' => SynchronizationStatusEnum::DONE,
            'messages' => $message,
        ]);

        foreach ($doneJobs as $doneJob) {
            $this->synchronizationJobRepository->remove($doneJob, true);
        }
    }

    private function createSyncJob(string $message): ?SynchronizationJob
    {
        $existingJob = $this->synchronizationJobRepository->findOneBy([
            'status' => SynchronizationStatusEnum::NOT_STARTED,
            'messages' => $message,
        ]);

        if ($existingJob) {
            return null;
        }

        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $job->setMessages($message);
        $this->synchronizationJobRepository->save($job, true);

        return $job;
    }
}
