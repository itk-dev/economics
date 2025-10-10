<?php

namespace App\Command;

use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'app:data-providers:sync-modified',
    description: 'Sync Data Provider data, that has been modified within the last hour, as jobs',
)]
#[AsPeriodicTask(frequency: '15 minutes')]
class SyncModifiedCommand extends Command
{
    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
    ) {
        parent::__construct($this->getName());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Look at entries modified within the last hour.
        $modifiedAfter = new \DateTime();
        $modifiedAfter->sub(new \DateInterval('PT1H'));

        $this->leantimeApiService->updateAll(true, $modifiedAfter);

        return Command::SUCCESS;
    }
}
