<?php

namespace App\Command;

use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:data-providers:sync-all',
    description: 'Sync all Data Provider data as jobs, scheduled at 01:15 each night.',
)]
#[AsCronTask('15 1 * * *')]
class SyncAllCommand extends Command
{
    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
    ) {
        parent::__construct($this->getName());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->leantimeApiService->updateAll(true);

        return Command::SUCCESS;
    }
}
