<?php

namespace App\Command;

use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Service\SyncService;
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
        private readonly MessageBusInterface $messageBus,
        private readonly SyncService $syncService,
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

        $queueLength = $this->syncService->countPendingJobsByQueueName('async');

        if ($queueLength > 0) {
            $io->error(sprintf('There are already %d jobs in the sync queue. Please wait until they are processed.', $queueLength));
            return false;
        }

        // Sync projects
        $this->dispatchDataProviderJobs(
            $io,
            $dataProviders,
            'Projects',
            fn ($dataProvider, $dataProviderId) => new SyncProjectsMessage($dataProviderId)
        );

        // Sync accounts
        $this->dispatchDataProviderJobs(
            $io,
            array_filter($dataProviders, fn ($dp) => $dp->isEnableAccountSync()),
            'Accounts',
            fn ($dataProvider, $dataProviderId) => new SyncAccountsMessage($dataProviderId)
        );

        // Sync issues and worklogs (project-based)
        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            // Sync issues
            $this->dispatchProjectJobs(
                $io,
                $dataProvider,
                $projects,
                'Issues',
                fn ($project, $dataProviderId) => new SyncProjectIssuesMessage(
                    $project->getProjectTrackerId(),
                    $dataProviderId
                )
            );

            // Sync worklogs
            $this->dispatchProjectJobs(
                $io,
                $dataProvider,
                $projects,
                'Worklogs',
                fn ($project, $dataProviderId) => new SyncProjectWorklogsMessage(
                    $project->getProjectTrackerId(),
                    $dataProviderId
                )
            );
        }

        return Command::SUCCESS;
    }

    private function dispatchDataProviderJobs(
        SymfonyStyle $io,
        array $dataProviders,
        string $type,
        callable $messageFactory,
    ): void {
        $io->info(sprintf('Dispatching %s sync jobs', strtolower($type)));

        foreach ($dataProviders as $dataProvider) {
            $dataProviderId = $dataProvider->getId();
            if (null === $dataProviderId) {
                continue;
            }

            $io->writeln(sprintf('Dispatching %s sync job for %s',
                strtolower($type),
                $dataProvider->getName()
            ));

            $message = $messageFactory($dataProvider, $dataProviderId);
            $this->messageBus->dispatch($message);
        }
    }

    private function dispatchProjectJobs(
        SymfonyStyle $io,
        $dataProvider,
        array $projects,
        string $type,
        callable $messageFactory,
    ): void {
        $io->info(sprintf('Dispatching %s sync jobs', strtolower($type)));

        foreach ($projects as $project) {
            $dataProviderId = $dataProvider->getId();
            if (null === $dataProviderId) {
                continue;
            }

            $io->writeln(sprintf('Dispatching %s sync job for %s',
                strtolower($type),
                $project->getName()
            ));

            $message = $messageFactory($project, $dataProviderId);
            $this->messageBus->dispatch($message);
        }
    }
}
