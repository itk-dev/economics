<?php

namespace App\Command;

use App\Repository\DataProviderRepository;
use App\Service\BillingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-accounts',
    description: 'Sync accounts',
)]
class SyncAccountsCommand extends Command
{
    public function __construct(
        private readonly BillingService         $billingService,
        private readonly DataProviderRepository $projectTrackerRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectTrackers = $this->projectTrackerRepository->findAll();

        foreach ($projectTrackers as $projectTracker) {
            $io->info("Processing accounts in " . $projectTracker->getName());

            $this->billingService->syncAccounts(function ($i, $length) use ($io) {
                if (0 == $i) {
                    $io->progressStart($length);
                } elseif ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            }, $projectTracker);
        }

        return Command::SUCCESS;
    }
}
