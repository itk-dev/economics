<?php

namespace App\Command;

use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Service\DataSynchronizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Message\SyncProjectWorklogsMessage;
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
        private readonly DataSynchronizationService $dataSynchronizationService,
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

        $io->info('Processing projects');

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $this->dataSynchronizationService->syncProjects(function ($i, $length) use ($io) {
                if (0 == $i) {
                    $io->progressStart($length);
                } elseif ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            }, $dataProvider);
        }

        $io->info('Processing accounts');

        foreach ($dataProviders as $dataProvider) {
            $this->dataSynchronizationService->syncAccounts(function ($i, $length) use ($io) {
                if (0 == $i) {
                    $io->progressStart($length);
                } elseif ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            }, $dataProvider);
        }

    /*    $io->info('Processing issues');

        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            foreach ($projects as $project) {
                $io->writeln("Processing issues for {$project->getName()}");

                $this->dataSynchronizationService->syncIssuesForProject($project->getId(), $dataProvider, function ($i, $length) use ($io) {
                    if (0 == $i) {
                        $io->progressStart($length);
                    } elseif ($i >= $length - 1) {
                        $io->progressFinish();
                    } else {
                        $io->progressAdvance();
                    }
                });

                $io->writeln('');
            }
        }*/

        // Replace the worklogs processing section with:
        $io->info('Dispatching worklog sync jobs');

        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            foreach ($projects as $project) {
                $io->writeln("Dispatching worklog sync job for {$project->getName()}");

                $message = new SyncProjectWorklogsMessage(
                    $project->getId(),
                    $dataProvider->getId()
                );

                $this->messageBus->dispatch($message);;
            }
        }


        return Command::SUCCESS;
    }
}
