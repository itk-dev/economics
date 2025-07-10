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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-worklogs',
    description: 'Sync worklogs for all projects.',
)]
class SyncWorklogsCommand extends Command
{
    public function __construct(
        private readonly DataProviderRepository     $dataProviderRepository,
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly ProjectRepository          $projectRepository,
    )
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
        $this
            ->addOption('project-id', null, InputOption::VALUE_OPTIONAL, 'Sync only specific project ID');
    }

    /**
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectId = $input->getOption('project-id');

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $criteria = ['include' => true, 'dataProvider' => $dataProvider];
            if ($projectId) {
                $criteria['projectTrackerId'] = $projectId;
            }
            $projects = $this->projectRepository->findBy($criteria);

           $numberOfProjects = count($projects);

            $io->info("Processing worklogs for $numberOfProjects projects that are included (project.include)");

            foreach ($projects as $project) {
                $io->writeln("Processing worklogs for {$project->getName()}");

                $this->dataSynchronizationService->syncWorklogsForProject($project->getId(), $dataProvider, function ($i, $length) use ($io) {
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
        }

        return Command::SUCCESS;
    }
}
