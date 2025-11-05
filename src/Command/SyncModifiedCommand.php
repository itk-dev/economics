<?php

namespace App\Command;

use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'app:data-providers:sync-modified',
    description: 'Sync Data Provider data, that has been modified within the given frequency, as jobs. Run frequency can be set with the DATA_PROVIDER_UPDATE_FREQUENCY environment variable.',
)]
#[AsPeriodicTask(frequency: '%env(DATA_PROVIDER_UPDATE_FREQUENCY)%', jitter: 30)]
class SyncModifiedCommand extends Command
{
    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
        $this->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Only consider items modified within the specified interval. See https://www.php.net/manual/en/dateinterval.construct.php for format.', 'PT1H');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = $input->getOption('interval');

        // Look at entries modified within the last hour.
        $modifiedAfter = new \DateTime();
        $modifiedAfter->sub(new \DateInterval($interval));

        $this->leantimeApiService->updateAll(true, $modifiedAfter);

        return Command::SUCCESS;
    }
}
