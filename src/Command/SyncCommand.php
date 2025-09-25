<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Enum\SynchronizationStepEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SynchronizationJobRepository;
use App\Service\DataSynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:sync',
    description: 'Sync all data.',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $managerRegistry,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $monitoringUrl,
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

        $job = new SynchronizationJob();
        $job->setStarted(new \DateTime());
        $job->addMessage('Synchronization started');
        $job->setProgress(0);
        $job->setStatus(SynchronizationStatusEnum::RUNNING);
        $this->synchronizationJobRepository->save($job, true);

        try {
            $io->info('Processing projects');
            $job->setStep(SynchronizationStepEnum::PROJECTS);

            $this->entityManager->flush();

            foreach ($dataProviders as $dataProvider) {
                $job->addMessage('Processing projects from '.$dataProvider->getName());
                $this->dataSynchronizationService->syncProjects(function ($i, $length) use ($io, $job) {
                    $this->setProgress($i, $length, $io, $job);
                }, $dataProvider);
            }

            $io->info('Processing accounts');
            $this->setStep(SynchronizationStepEnum::ACCOUNTS, $job);

            foreach ($dataProviders as $dataProvider) {
                $this->dataSynchronizationService->syncAccounts(function ($i, $length) use ($io, $job) {
                    $this->setProgress($i, $length, $io, $job);
                }, $dataProvider);
            }

            $io->info('Processing issues');
            $this->setStep(SynchronizationStepEnum::ISSUES, $job);

            foreach ($dataProviders as $dataProvider) {
                $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

                foreach ($projects as $project) {
                    $io->writeln("Processing issues for {$project->getName()}");

                    $this->dataSynchronizationService->syncIssuesForProject($project->getId(), $dataProvider, function ($i, $length) use ($io, $job) {
                        $this->setProgress($i, $length, $io, $job);
                    });

                    $io->writeln('');
                }
            }

            $io->info('Processing worklogs');
            $this->setStep(SynchronizationStepEnum::WORKLOGS, $job);

            foreach ($dataProviders as $dataProvider) {
                $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

                /** @var Project $project */
                foreach ($projects as $project) {
                    $io->writeln("Processing worklogs for {$project->getName()}");

                    $projectId = $project->getId();

                    if (null === $projectId) {
                        throw new \RuntimeException('Project id is null');
                    }

                    $this->dataSynchronizationService->syncWorklogsForProject($projectId, $dataProvider, function ($i, $length) use ($io, $job) {
                        $this->setProgress($i, $length, $io, $job);
                    });

                    $io->writeln('');
                }
            }

            $job = $this->getJob($job);
            $job->setStatus(SynchronizationStatusEnum::DONE);
            $job->setEnded(new \DateTime());
            $this->entityManager->flush();

            // Call monitoring url if defined.
            if ('' !== $this->monitoringUrl) {
                try {
                    $this->client->request('GET', $this->monitoringUrl);
                } catch (\Throwable $e) {
                    $this->logger->error('Error calling monitoringUrl: '.$e->getMessage());
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $this->managerRegistry->resetManager();

            $job = $this->getJob($job);
            $job->setStatus(SynchronizationStatusEnum::ERROR);
            $job->setEnded(new \DateTime());
            $job->addMessage($exception->getMessage());

            $this->entityManager->flush();

            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function setProgress(int $i, int $length, SymfonyStyle $io, SynchronizationJob $job): void
    {
        $job = $this->getJob($job);

        if (0 == $i) {
            $io->progressStart($length);
            $job->setProgress($i);
        } elseif ($i >= $length - 1) {
            $io->progressFinish();
            $job->setProgress(100);
        } else {
            $length = 0 < $length ? $length : 1;
            $io->progressAdvance();
            $job->setProgress(intdiv($i * 100, $length));
        }
    }

    private function setStep(SynchronizationStepEnum $step, SynchronizationJob $job): void
    {
        $job = $this->getJob($job);

        $job->setStep($step);
        $this->entityManager->flush();
    }

    private function getJob(SynchronizationJob $job): SynchronizationJob
    {
        if (!$this->entityManager->contains($job)) {
            $job = $this->synchronizationJobRepository->find($job->getId());

            if (null === $job) {
                throw new \RuntimeException('Job not found');
            }
        }

        return $job;
    }
}
