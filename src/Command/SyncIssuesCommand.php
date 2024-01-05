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

#[AsCommand(
    name: 'app:sync-issues',
    description: 'Sync worklogs for all projects.',
)]
class SyncIssuesCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly DataSynchronizationService $dataSynchronizationService,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dataProviders = $this->dataProviderRepository->findAll();

        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            $numberOfProjects = count($projects);

            $io->info("Processing issues for $numberOfProjects projects that are included (project.include)");

            foreach ($projects as $project) {
                $io->writeln("Processing issues for {$project->getName()}");

                $this->dataSynchronizationService->syncIssuesForProject($project->getId(), function ($i, $length) use ($io) {
                    if (0 == $i) {
                        $io->progressStart($length);
                    } elseif ($i >= $length - 1) {
                        $io->progressFinish();
                    } else {
                        $io->progressAdvance();
                    }
                }, $dataProvider);

                $io->writeln('');
            }
        }

        return Command::SUCCESS;
    }
}
