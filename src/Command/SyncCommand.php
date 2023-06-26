<?php

namespace App\Command;

use App\Repository\ProjectRepository;
use App\Service\BillingService;
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
        private readonly BillingService $billingService,
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

        $io->info('Processing projects');

        $this->billingService->syncProjects(function ($i, $length) use ($io) {
            if (0 == $i) {
                $io->progressStart($length);
            } elseif ($i >= $length - 1) {
                $io->progressFinish();
            } else {
                $io->progressAdvance();
            }
        });

        $io->info('Processing accounts');

        $this->billingService->syncAccounts(function ($i, $length) use ($io) {
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

            $this->billingService->syncIssuesForProject($project->getId(), function ($i, $length) use ($io) {
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

            $this->billingService->syncWorklogsForProject($project->getId(), function ($i, $length) use ($io) {
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