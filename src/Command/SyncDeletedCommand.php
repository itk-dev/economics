<?php

namespace App\Command;

use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'app:data-providers:sync-deleted',
    description: 'Sync Data Provider deleted data, that has been deleted within the last hour, as jobs. Scheduled to run every 15 minutes.',
)]
#[AsPeriodicTask(frequency: '15 minutes')]
class SyncDeletedCommand extends Command
{
    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
    ) {
        parent::__construct($this->getName());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Look at entries modified within the last hour.
        $deletedAfter = new \DateTime();
        $deletedAfter->sub(new \DateInterval('P1D'));

        $this->leantimeApiService->updateAll(true, $deletedAfter);

        return Command::SUCCESS;
    }
}
