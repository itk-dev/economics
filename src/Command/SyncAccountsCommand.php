<?php

namespace App\Command;

use App\Service\DataProviderService;
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
    public function __construct(private readonly DataProviderService $dataProviderService)
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->dataProviderService->syncAccounts(function ($i, $length) use ($io) {
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
