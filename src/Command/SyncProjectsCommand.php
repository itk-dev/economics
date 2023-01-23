<?php

namespace App\Command;

use App\Service\BillingService;
use App\Service\ProjectTracker\ApiServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-projects',
    description: 'Add a short description for your command',
)]
class SyncProjectsCommand extends Command
{
    public function __construct(private readonly BillingService $billingService)
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->progressStart(277);
        $this->billingService->syncProjects(function() use ($io) {
            $io->progressAdvance();
        });
        $io->progressFinish();

        return Command::SUCCESS;
    }
}
