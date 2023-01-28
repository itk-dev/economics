<?php

namespace App\Command;

use App\Repository\ProjectRepository;
use App\Service\Invoices\BillingService;
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
        private readonly BillingService $billingService,
        private readonly ProjectRepository $projectRepository,
    ){
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

        $projects = $this->projectRepository->findAll();

        foreach ($projects as $project) {
            $io->writeln("Processing worklogs for {$project->getName()}");

            $this->billingService->syncWorklogsForProject($project->getId(), function($i, $length) use ($io) {
                if ($i == 0) {
                    $io->progressStart($length);
                } else if ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            });

            $io->writeln("");
        }

        return Command::SUCCESS;
    }
}
