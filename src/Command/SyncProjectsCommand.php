<?php

namespace App\Command;

use App\Repository\DataProviderRepository;
use App\Service\SyncService;
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
        private readonly DataProviderRepository     $dataProviderRepository,
        private readonly SyncService                $syncService,
    )
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $queueLength = $this->syncService->countPendingJobsByQueueName('async');

        if ($queueLength > 0) {
            $io->error(sprintf('There are already %d jobs in the sync queue. Please wait until they are processed.', $queueLength));
            return Command::INVALID;
        }

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        $this->syncService->syncProjects($dataProviders, $io);

        return Command::SUCCESS;
    }
}
