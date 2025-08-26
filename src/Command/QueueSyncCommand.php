<?php

namespace App\Command;

use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use App\Message\SynchronizeMessage;
use App\Repository\SynchronizationJobRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:queue-sync',
    description: 'Add a synchronization job to the queue.',
)]
// TODO: re-enable cron job when queue is fully asynchronous
// #[AsCronTask(expression: '0 0 * * *', schedule: 'default')]
class QueueSyncCommand extends Command
{
    public function __construct(
        private readonly SynchronizationJobRepository $synchronizationJobRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $job = new SynchronizationJob();
        $job->setStatus(SynchronizationStatusEnum::NOT_STARTED);
        $this->synchronizationJobRepository->save($job, true);

        $jobId = $job->getId();

        if (null === $jobId) {
            return Command::FAILURE;
        }

        $message = new SynchronizeMessage($jobId);

        $this->bus->dispatch($message);

        return Command::SUCCESS;
    }
}
