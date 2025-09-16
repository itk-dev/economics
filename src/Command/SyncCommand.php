<?php

namespace App\Command;

use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\DataProviderRepository;
use App\Service\SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:sync',
    description: 'Sync all data or specific type (projects, accounts, issues, worklogs).',
)]
#[AsCronTask(expression: '0 1 * * *', schedule: 'default')]
class SyncCommand extends Command
{
    private const VALID_TYPES = ['projects', 'accounts', 'issues', 'worklogs'];

    public function __construct(
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly SyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'type',
            InputArgument::OPTIONAL,
            'Sync type: projects, accounts, issues, worklogs (if not specified, syncs all)',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');

        // Validate type if provided
        if ($type !== null && !in_array($type, self::VALID_TYPES)) {
            $io->error(sprintf('Invalid sync type "%s". Valid types are: %s', $type, implode(', ', self::VALID_TYPES)));
            return Command::INVALID;
        }
g
        $queueLength = $this->syncService->countPendingJobsByQueueName('async');
        if ($queueLength > 0) {
            $io->error(sprintf('There are already %d jobs in the sync queue. Please wait until they are processed.', $queueLength));
            return Command::INVALID;
        }

        $dataProviders = $this->dataProviderRepository->findBy(['enabled' => true]);

        // If no type specified, sync all
        if ($type === null) {
            $this->syncService->syncProjects($dataProviders, $io);
            $this->syncService->syncAccounts($dataProviders, $io);
            $this->syncService->syncIssues($dataProviders, $io);
            $this->syncService->syncWorklogs($dataProviders, $io);
        } else {
            // Sync only the specified type
            match ($type) {
                'projects' => $this->syncService->syncProjects($dataProviders, $io),
                'accounts' => $this->syncService->syncAccounts($dataProviders, $io),
                'issues' => $this->syncService->syncIssues($dataProviders, $io),
                'worklogs' => $this->syncService->syncWorklogs($dataProviders, $io),
            };
        }

        return Command::SUCCESS;
    }
}
