<?php

namespace App\Command;

use App\Exception\EconomicsException;
use App\Repository\ProjectRepository;
use App\Service\DataProviderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync',
    description: 'Sync all data.',
)]
class SyncCommand extends Command
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
     * @throws EconomicsException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projects = $this->projectRepository->findBy(['include' => true]);

        $io->info('Processing projects');

        $this->dataProviderService->syncProjects(function ($i, $length) use ($io) {
            if (0 == $i) {
                $io->progressStart($length);
            } elseif ($i >= $length - 1) {
                $io->progressFinish();
            } else {
                $io->progressAdvance();
            }
        });

        $io->info('Processing accounts');

        $this->dataProviderService->syncAccounts(function ($i, $length) use ($io) {
            if (0 == $i) {
                $io->progressStart($length);
            } elseif ($i >= $length - 1) {
                $io->progressFinish();
            } else {
                $io->progressAdvance();
            }
        });

        $io->info('Processing issues');

        foreach ($projects as $project) {
            $io->writeln("Processing issues for {$project->getName()}");

            $this->dataProviderService->syncIssuesForProject($project->getId(), function ($i, $length) use ($io) {
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

        $io->info('Processing worklogs');

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
