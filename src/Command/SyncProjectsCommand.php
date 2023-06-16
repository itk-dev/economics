<?php

namespace App\Command;

use App\Service\BillingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-projects',
    description: 'Sync projects',
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

        $this->billingService->syncProjects(function ($i, $length) use ($io) {
            if (0 == $i) {
                $io->progressStart($length);
            } elseif ($i >= $length - 1) {
                $io->progressFinish();
            } else {
                $io->progressAdvance();
            }
        });

        return Command::SUCCESS;
    }
}
