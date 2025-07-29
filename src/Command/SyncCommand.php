<?php

namespace App\Command;

use App\Entity\DataProvider;
use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SynchronizationJobRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:sync',
    description: 'Sync all data.',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        // Sync projects
        $this->dispatchJobs(
            $io,
            $dataProviders,
            'Projects',
            fn ($item, $dataProviderId, $jobId) => new SyncProjectsMessage($dataProviderId, $jobId),
            fn ($type, $item, $dataProviderId) => sprintf('%s - dataProviderId: %d', $type, $dataProviderId),
            fn ($item) => $item->getName()
        );

        // Sync accounts
        $this->dispatchJobs(
            $io,
            array_filter($dataProviders, fn ($dp) => $dp->isEnableAccountSync()),
            'Accounts',
            fn ($item, $dataProviderId, $jobId) => new SyncAccountsMessage($dataProviderId, $jobId),
            fn ($type, $item, $dataProviderId) => sprintf('%s - dataProviderId: %d', $type, $dataProviderId),
            fn ($item) => $item->getName()
        );

        // Sync issues and worklogs (project-based)
        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            // Sync issues
            $this->dispatchJobs(
                $io,
                $projects,
                'Issues',
                fn ($item, $dataProviderId, $jobId) => new SyncProjectIssuesMessage(
                    $item->getProjectTrackerId(),
                    $dataProviderId,
                    $jobId
                ),
                fn ($type, $item, $dataProviderId) => sprintf('%s - projectId: %d dataProviderId: %d',
                    $type,
                    $item->getProjectTrackerId(),
                    $dataProviderId
                ),
                fn ($item) => $item->getName()
            );

            // Sync worklogs
            $this->dispatchJobs(
                $io,
                $projects,
                'Worklogs',
                fn ($item, $dataProviderId, $jobId) => new SyncProjectWorklogsMessage(
                    $item->getProjectTrackerId(),
                    $dataProviderId,
                    $jobId
                ),
                fn ($type, $item, $dataProviderId) => sprintf('%s - projectId: %d dataProviderId: %d',
                    $type,
                    $item->getProjectTrackerId(),
                    $dataProviderId
                ),
                fn ($item) => $item->getName()
            );
        }

        return Command::SUCCESS;
    }

    private function dispatchJobs(
        SymfonyStyle $io,
        array $items,
        string $type,
        callable $messageFactory,
        callable $messageFormatter,
        callable $getName,
    ): void {
        $io->info(sprintf('Dispatching %s sync jobs', strtolower($type)));

        foreach ($items as $item) {
            $dataProviderId = $item instanceof DataProvider ? $item->getId() : $item->getDataProvider()->getId();
            if (null === $dataProviderId) {
                continue;
            }

            $message = $messageFormatter($type, $item, $dataProviderId);

            // Delete completed jobs
            $this->deleteCompletedJobs($message);

            $job = $this->createSyncJob($message);
            if (null === $job) {
                $io->writeln(sprintf('Duplicate %s sync job already exists for %s',
                    strtolower($type),
                    $getName($item)
                ));
                continue;
            }

            $io->writeln(sprintf('Dispatching %s sync job for %s',
                strtolower($type),
                $getName($item)
            ));

            $message = $messageFactory($item, $dataProviderId, $job->getId());
            $this->messageBus->dispatch($message);
        }
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
