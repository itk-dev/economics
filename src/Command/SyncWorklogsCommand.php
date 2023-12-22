<?php

namespace App\Command;

use App\Repository\ProjectRepository;
use App\Service\BillingService;
use App\Service\DataProviderService;
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
        private readonly DataProviderService $dataProviderService,
        private readonly ProjectRepository $projectRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projects = $this->projectRepository->findBy(['include' => true]);

        $numberOfProjects = count($projects);

        $io->info("Processing worklogs for $numberOfProjects projects that are included (project.include)");

        foreach ($projects as $project) {
            $io->writeln("Processing worklogs for {$project->getName()}");

            $this->dataProviderService->syncWorklogsForProject($project->getId(), function ($i, $length) use ($io) {
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

        return Command::SUCCESS;
    }
}
