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
    name: 'app:sync-worklogs',
    description: 'Sync worklogs for all projects.',
)]
class SyncWorklogsCommand extends Command
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly ProjectRepository $projectRepository,
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

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $projects = $this->projectRepository->findBy(['include' => true, 'dataProvider' => $dataProvider]);

            $numberOfProjects = count($projects);

            $io->info("Processing worklogs for $numberOfProjects projects that are included (project.include)");

            foreach ($projects as $project) {
                var_dump('Max memory limit: '.ini_get('memory_limit'));
                var_dump('Memory usage: '.(memory_get_usage() / 1024 / 1024).' MB');
                $io->writeln("Processing worklogs for {$project->getName()}");

                $this->dataSynchronizationService->syncWorklogsForProject($project->getId(), function ($i, $length) use ($io) {
                    if (0 == $i) {
                        $io->progressStart($length);
                    }

                    if ($i < $length) {
                        $io->progressAdvance();
                    }

                    if ($i == $length - 1) {
                        $io->progressFinish();
                    }
                }, $dataProvider);

                $io->writeln('');
            }
        }

        return Command::SUCCESS;
    }
}
