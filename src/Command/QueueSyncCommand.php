<?php

namespace App\Command;

use App\Message\SynchronizeMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:queue-sync',
    description: 'Add a synchronization to the queue.',
)]
#[AsCronTask(expression: '0 0 * * *', schedule: 'default')]
class QueueSyncCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = new SynchronizeMessage(1);
        $this->bus->dispatch($message);

        return Command::SUCCESS;
    }
}
