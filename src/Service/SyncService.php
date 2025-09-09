<?php

namespace App\Service;

use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SyncService
{
    public function __construct(
        private ProjectRepository      $projectRepository,
        private DataProviderRepository $dataProviderRepository,
        private MessageBusInterface    $messageBus,
    )
    {
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
        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        // Sync projects for all data providers
        foreach ($dataProviders as $dataProvider) {
            $dataProviderId = $dataProvider->getId();
            if (!$dataProviderId) {
                continue;
            }

            $providerName = $dataProvider->getName();
            if (null !== $providerName) {
                $io?->info(sprintf('Syncing projects for provider %s', $providerName));
                $this->messageBus->dispatch(new SyncProjectsMessage($dataProviderId, 0));
            }

            // Sync accounts for enabled providers
            if ($dataProvider->isEnableAccountSync()) {
                if (null !== $providerName) {
                    $io?->info(sprintf('Syncing accounts for provider %s', $providerName));
                    $this->messageBus->dispatch(new SyncAccountsMessage($dataProviderId, 0));
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
                    $projectName = $project->getName();

                    if (null !== $projectName) {
                        // Sync issues
                        $io?->info(sprintf('Dispatching sync for issues for project: %s', $projectName));
                        $this->messageBus->dispatch(new SyncProjectIssuesMessage($projectId, $dataProviderId, 0));

                        // Sync worklogs
                        $io?->info(sprintf('Dispatching sync for worklogs for project: %s', $projectName));
                        $this->messageBus->dispatch(new SyncProjectWorklogsMessage($projectId, $dataProviderId, 0));
                    }
                }
            }
        }
    }
}
