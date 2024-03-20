<?php

namespace App\Command;

use App\Exception\UnsupportedDataProviderException;
use App\Repository\DataProviderRepository;
use App\Service\DataSynchronizationService;
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
        private readonly DataSynchronizationService $dataSynchronizationService,
        private readonly DataProviderRepository $dataProviderRepository,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * @throws UnsupportedDataProviderException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $io->info('Processing accounts in '.$dataProvider->getName());

            $this->dataSynchronizationService->syncAccounts(function ($i, $length) use ($io) {
                if (0 == $i) {
                    $io->progressStart($length);
                } elseif ($i >= $length - 1) {
                    $io->progressFinish();
                } else {
                    $io->progressAdvance();
                }
            }, $dataProvider);
        }

        return Command::SUCCESS;
    }
}
