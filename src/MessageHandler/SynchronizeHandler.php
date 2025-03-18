<?php

namespace App\MessageHandler;

use App\Enum\SynchronizationStatusEnum;
use App\Enum\SynchronizationStepEnum;
use App\Message\SynchronizeMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class SynchronizeHandler
{
    public function __construct(
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $monitoringUrl,
        private readonly LockFactory $factory,
    ) {
    }

    public function __invoke(SynchronizeMessage $message): void
    {
        // TTL for the lock is two hours.
        $lock = $this->factory->createLock('sync-all', ttl: 60 * 60 * 2);

        try {
            if (!$lock->acquire()) {
                $this->logger->warning('Lock could not be acquired.');

                return;
            }

            $this->logger->info('sync-all lock acquired');

            $job = $this->synchronizationJobRepository->find($message->getSynchronizationJobId());

            if (null === $job) {
                throw new \Exception('Job not found', 404);
            }

            $job->setStarted(new \DateTime());
            $job->setMessages('Synchronization started');
            $job->setProgress(0);
            $job->setStatus(SynchronizationStatusEnum::RUNNING);
            $this->synchronizationJobRepository->save($job, true);

            $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

            $job->setStep(SynchronizationStepEnum::PROJECTS);
            $job->setMessages($job->getMessages()."\nSynchronizing projects");
            $this->synchronizationJobRepository->save($job, true);
            foreach ($dataProviders as $dataProvider) {
                $job->setMessages($job->getMessages()."\nProcessing projects from ".$dataProvider->getName());
                $this->synchronizationJobRepository->save($job, true);

                $this->dataSynchronizationService->syncProjects(function ($i, $length) use ($job) {
                    $job->setProgress($i * 100 / ($length ?? 1));
                    $this->synchronizationJobRepository->save($job, true);
                }, $dataProvider);
            }

            $job->setStep(SynchronizationStepEnum::ACCOUNTS);
            $job->setMessages($job->getMessages()."\nSynchronizing accounts");
            $this->synchronizationJobRepository->save($job, true);
            foreach ($dataProviders as $dataProvider) {
                $job->setMessages($job->getMessages()."\nProcessing accounts from ".$dataProvider->getName());
                $this->synchronizationJobRepository->save($job, true);

                $this->dataSynchronizationService->syncAccounts(function ($i, $length) use ($job) {
                    $job->setProgress($i * 100 / ($length ?? 1));
                    $this->synchronizationJobRepository->save($job, true);
                }, $dataProvider);
            }

            $job->setStep(SynchronizationStepEnum::ISSUES);
            $job->setMessages($job->getMessages()."\nSynchronizing issues");
            $this->synchronizationJobRepository->save($job, true);
            foreach ($dataProviders as $dataProvider) {
                $job->setMessages($job->getMessages()."\nProcessing issues from ".$dataProvider->getName());
                $this->synchronizationJobRepository->save($job, true);

                $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

                foreach ($projects as $project) {
                    $this->dataSynchronizationService->syncIssuesForProject($project->getId(), $dataProvider, function ($i, $length) use ($job) {
                        $job->setProgress($i * 100 / ($length ?? 1));
                        $this->synchronizationJobRepository->save($job, true);
                    });
                }
            }

            $job->setStep(SynchronizationStepEnum::WORKLOGS);
            $job->setMessages($job->getMessages()."\nSynchronizing worklogs");
            $this->synchronizationJobRepository->save($job, true);
            foreach ($dataProviders as $dataProvider) {
                $job->setMessages($job->getMessages()."\nProcessing worklogs from ".$dataProvider->getName());
                $this->synchronizationJobRepository->save($job, true);

                $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

                $projectsSynced = 0;
                $numberOfProjects = count($projects);
                foreach ($projects as $project) {
                    $this->dataSynchronizationService->syncWorklogsForProject($project->getId(), $dataProvider);

                    ++$projectsSynced;
                    $job->setProgress((int) ($projectsSynced * 100 / $numberOfProjects));
                    $this->synchronizationJobRepository->save($job, true);
                }
            }

            $job->setStatus(SynchronizationStatusEnum::DONE);
            $job->setEnded(new \DateTime());
            $this->synchronizationJobRepository->save($job, true);

            // Call monitoring url if defined.
            if ('' !== $this->monitoringUrl) {
                try {
                    $this->client->request('GET', $this->monitoringUrl);
                } catch (\Throwable $e) {
                    $this->logger->error('Error calling monitoringUrl: '.$e->getMessage());
                }
            }
        } catch (\Throwable $throwable) {
            $job = $this->synchronizationJobRepository->find($message->getSynchronizationJobId());

            if (null !== $job) {
                $job->setStatus(SynchronizationStatusEnum::ERROR);
                $job->setEnded(new \DateTime());
                $this->synchronizationJobRepository->save($job, true);
            }

            // Avoid retrying job.
            throw new UnrecoverableMessageHandlingException($throwable->getMessage(), (int) $throwable->getCode() ?? 0);
        } finally {
            $lock->release();
            $this->logger->info('sync-all lock released.');
        }
    }
}
