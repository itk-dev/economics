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
    name: 'app:sync-projects',
    description: 'Sync projects',
)]
class SyncProjectsCommand extends Command
{
    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly DataSynchronizationService $dataSynchronizationService,
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

        $dataProviders = $this->dataProviderRepository->findAll();

        foreach ($dataProviders as $dataProvider) {
            $this->dataSynchronizationService->syncProjects(function ($i, $length) use ($io) {
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
