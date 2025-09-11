<?php

namespace App\Service;

use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

readonly class SyncService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private DataProviderRepository $dataProviderRepository,
        private MessageBusInterface $messageBus,
        private ContainerInterface $transportLocator,
    ) {
    }

    /**
     * Synchronizes projects, accounts, issues, and worklogs for all enabled data providers.
     *
     * @param SymfonyStyle|null $io optional SymfonyStyle instance for console output
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
                $this->messageBus->dispatch(new SyncProjectsMessage($dataProviderId));
            }

            // Sync accounts for enabled providers
            if ($dataProvider->isEnableAccountSync()) {
                if (null !== $providerName) {
                    $io?->info(sprintf('Syncing accounts for provider %s', $providerName));
                    $this->messageBus->dispatch(new SyncAccountsMessage($dataProviderId));
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
                        $this->messageBus->dispatch(new SyncProjectIssuesMessage($projectId, $dataProviderId));

                        // Sync worklogs
                        $io?->info(sprintf('Dispatching sync for worklogs for project: %s (%s)', $projectName, $projectId));
                        $this->messageBus->dispatch(new SyncProjectWorklogsMessage($projectId, $dataProviderId));
                    }
                }
            }
        }
    }

    /**
     * Counts the number of pending jobs for a specific queue based on the transport name.
     *
     * @param string $transportName the name of the transport (queue) to count pending jobs for
     *
     * @return int the number of pending jobs in the specified queue
     *
     * @throws \RuntimeException         if the specified transport does not support message count functionality
     * @throws \InvalidArgumentException if the specified transport does not exist
     */
    public function countPendingJobsByQueueName(string $transportName): int
    {
        if (!$this->transportLocator->has($transportName)) {
            throw new \InvalidArgumentException('The transport does not exist.');
        }

        $transport = $this->transportLocator->get($transportName);
        if (!$transport instanceof MessageCountAwareInterface) {
            throw new \RuntimeException('The transport is not message count aware.');
        }

        return $transport->getMessageCount();
    }
}
