<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\ProjectRepository;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

readonly class SyncService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MessageBusInterface $messageBus,
        private ContainerInterface $transportLocator,
    ) {
    }

    /**
     * @param DataProvider[] $dataProviders
     */
    public function syncProjects(array $dataProviders, SymfonyStyle $io): void
    {
        $io->info('Dispatching projects sync jobs');

        foreach ($dataProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                continue;
            }
            $this->dispatchJob(
                new SyncProjectsMessage($providerId),
                "projects sync job for {$dataProvider->getName()}",
                $io
            );
        }
    }

    /**
     * @param DataProvider[] $dataProviders
     */
    public function syncAccounts(array $dataProviders, SymfonyStyle $io): void
    {
        $enabledProviders = array_filter($dataProviders, fn ($dp) => $dp->isEnableAccountSync());

        if (empty($enabledProviders)) {
            $io->error('No data providers with account sync is enabled.');
        }

        $io->info('Dispatching accounts sync jobs');

        foreach ($enabledProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                continue;
            }
            $this->dispatchJob(
                new SyncAccountsMessage($providerId),
                "accounts sync job for {$dataProvider->getName()}",
                $io
            );
        }
    }

    /**
     * @param DataProvider[] $dataProviders
     */
    public function syncIssues(array $dataProviders, SymfonyStyle $io): void
    {
        $io->info('Dispatching issues sync jobs');

        foreach ($dataProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                continue;
            }
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            foreach ($projects as $project) {
                $this->dispatchJob(
                    new SyncProjectIssuesMessage($project->getId(), $providerId),
                    "issues sync job for {$project->getName()}",
                    $io
                );
            }
        }
    }

    /**
     * @param DataProvider[] $dataProviders
     */
    public function syncWorklogs(array $dataProviders, SymfonyStyle $io): void
    {
        $io->info('Dispatching worklogs sync jobs');

        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                continue;
            }

            foreach ($projects as $project) {
                $this->dispatchJob(
                    new SyncProjectWorklogsMessage($project->getId(), $providerId),
                    "worklogs sync job for {$project->getName()}",
                    $io
                );
            }
        }
    }

    private function dispatchJob(object $message, string $description, SymfonyStyle $io): void
    {
        $io->writeln("Dispatching {$description}");
        $this->messageBus->dispatch($message);
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
