<?php

namespace App\Service;

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

    /**
     * Synchronizes projects, accounts, issues, and worklogs for all enabled data providers.
     *
     * @param SymfonyStyle|null $io optional SymfonyStyle instance for console output
     *
     * @return void
     */
    public function sync(?SymfonyStyle $io = null): void
    {
        if (null === $io) {
            return;
        }

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        // Sync projects for all data providers
        foreach ($dataProviders as $dataProvider) {
            $dataProviderId = $dataProvider->getId();
            if (!$dataProviderId) {
                continue;
            }
            $message = sprintf('Projects - dataProviderId: %d', $dataProviderId);
            $job = $this->createJob($message, $io);
            $providerName = $dataProvider->getName();
            $jobId = $job?->getId();

            if (null !== $job && null !== $jobId && null !== $providerName) {
                $io->info(sprintf('Syncing projects for provider %s', $providerName));
                $this->messageBus->dispatch(new SyncProjectsMessage($dataProviderId, $jobId));
            }

            // Sync accounts for enabled providers
            if ($dataProvider->isEnableAccountSync()) {
                $message = sprintf('Accounts - dataProviderId: %d', $dataProviderId);
                $job = $this->createJob($message, $io);
                $providerName = $dataProvider->getName();
                $jobId = $job?->getId();

                if (null !== $job && null !== $jobId && null !== $providerName) {
                    $io->info(sprintf('Syncing accounts for provider %s', $providerName));
                    $this->messageBus->dispatch(new SyncAccountsMessage($dataProviderId, $jobId));
                }
            }
        }

        // Sync issues and worklogs for each project
        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            foreach ($projects as $project) {
                $projectId = $project->getProjectTrackerId();
                $dataProviderId = $dataProvider->getId();

                if (null !== $projectId && null !== $dataProviderId) {
                    // Sync issues
                    $message = sprintf('Issues - projectId: %d dataProviderId: %d', $projectId, $dataProviderId);
                    $job = $this->createJob($message, $io);
                    $jobId = $job?->getId();
                    $projectName = $project->getName();

                    if (null !== $job && null !== $jobId && null !== $projectName) {
                        $io->info(sprintf('Dispatched job for syncing issues for project: %s', $projectName));
                        $this->messageBus->dispatch(new SyncProjectIssuesMessage($projectId, $dataProviderId, $jobId));
                    }

                    // Sync worklogs
                    $message = sprintf('Worklogs - projectId: %d dataProviderId: %d', $projectId, $dataProviderId);
                    $job = $this->createJob($message, $io);
                    $jobId = $job?->getId();
                    $projectName = $project->getName();

                    if (null !== $job && null !== $jobId && null !== $projectName) {
                        $io->info(sprintf('Dispatched job for syncing worklogs for project: %s', $projectName));
                        $this->messageBus->dispatch(new SyncProjectWorklogsMessage($projectId, $dataProviderId, $jobId));
                    }
                }
            }
        }
    }

    /**
     * Determines if a new synchronization process can be started
     * by checking the status of the latest job in the database.
     *
     * @return bool true if a new synchronization can be started, false otherwise
     */
    public function canStartNewSync(): bool
    {
        $latestJob = $this->synchronizationJobRepository->getLatestJob();

        return null === $latestJob || !in_array($latestJob->getStatus(), [
            SynchronizationStatusEnum::NOT_STARTED,
            SynchronizationStatusEnum::RUNNING,
        ]);
    }

    /**
     * Creates and initializes a new synchronization job.
     *
     * @return SynchronizationJob|null the created synchronization job, or null if the creation fails
     */
    public function createJob(string $message, SymfonyStyle $io): ?SynchronizationJob
    {
        // Clean up completed jobs with the same message
        $doneJobs = $this->synchronizationJobRepository->findBy([
            'status' => SynchronizationStatusEnum::DONE,
            'messages' => $message,
        ]);

        foreach ($doneJobs as $doneJob) {
            $this->synchronizationJobRepository->remove($doneJob, true);
        }

        // Check for existing not started job
        $existingJob = $this->synchronizationJobRepository->findOneBy([
            'status' => SynchronizationStatusEnum::NOT_STARTED,
            'messages' => $message,
        ]);
        if ($existingJob) {
            $io->info(sprintf('Duplicate job exists for syncing %s', $message));

            return null;
        }

        // Create new job
        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $job->setMessages($message);
        $this->synchronizationJobRepository->save($job, true);

        return $job;
    }
}
