<?php

namespace App\Service;

use App\Message\SyncAccountsMessage;
use App\Message\SyncProjectIssuesMessage;
use App\Message\SyncProjectsMessage;
use App\Message\SyncProjectWorklogsMessage;
use App\Repository\ProjectRepository;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

readonly class SyncService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MessageBusInterface $messageBus,
        private ContainerInterface $transportLocator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatch jobs for syncing projects.
     *
     * @param array $dataProviders Array of data providers to sync worklogs for
     *
     * @return void the number of pending jobs in the specified queue
     */
    public function syncProjects(array $dataProviders): void
    {
        foreach ($dataProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                $this->logger->error('Data provider ID is null.', ['dataProvider' => $dataProvider]);
                continue;
            }
            $this->dispatchJob(
                new SyncProjectsMessage($providerId),
            );
        }
    }

    /**
     * Dispatch jobs for syncing accounts.
     *
     * @param array $dataProviders Array of data providers to sync worklogs for
     *
     * @return void the number of pending jobs in the specified queue
     */
    public function syncAccounts(array $dataProviders): void
    {
        $enabledProviders = array_filter($dataProviders, fn ($dp) => $dp->isEnableAccountSync());

        if (empty($enabledProviders)) {
            $this->logger->error('No data providers with account sync is enabled.');
        }

        foreach ($enabledProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                $this->logger->error('Data provider ID is null.', ['dataProvider' => $dataProvider]);
                continue;
            }
            $this->dispatchJob(
                new SyncAccountsMessage($providerId),
            );
        }
    }

    /**
     * Dispatch jobs for syncing issues.
     *
     * @param array $dataProviders Array of data providers to sync worklogs for
     *
     * @return void the number of pending jobs in the specified queue
     */
    public function syncIssues(array $dataProviders): void
    {
        foreach ($dataProviders as $dataProvider) {
            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                $this->logger->error('Data provider ID is null.', ['dataProvider' => $dataProvider]);
                continue;
            }
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            foreach ($projects as $project) {
                $this->dispatchJob(
                    new SyncProjectIssuesMessage($project->getId(), $providerId),
                );
            }
        }
    }

    /**
     *  Dispatch jobs for syncing worklogs.
     *
     * @param array $dataProviders Array of data providers to sync worklogs for
     *
     * @return void the number of pending jobs in the specified queue
     */
    public function syncWorklogs(array $dataProviders): void
    {
        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            $providerId = $dataProvider->getId();
            if (null === $providerId) {
                $this->logger->error('Data provider ID is null.', ['dataProvider' => $dataProvider]);
                continue;
            }

            foreach ($projects as $project) {
                $this->dispatchJob(
                    new SyncProjectWorklogsMessage($project->getId(), $providerId),
                );
            }
        }
    }

    /**
     * Dispatches a given job.
     *
     * @param object $message The job to dispatch
     *
     * @return void the number of pending jobs in the specified queue
     */
    private function dispatchJob(object $message): void
    {
        $this->messageBus->dispatch($message);
    }

    /**
     * Counts the number of pending jobs for a specific queue based on the transport name.
     *
     * @param string $transportName The name of the transport (queue) to count pending jobs for
     *
     * @return int The number of pending jobs in the specified queue
     *
     * @throws ContainerExceptionInterface
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
