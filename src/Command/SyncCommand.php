<?php

namespace App\Command;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Service\LeantimeApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:data-providers:sync',
    description: 'Sync Data Provider data',
)]
class SyncCommand extends Command
{
    private const OPTION_JOB = 'job';
    private const OPTION_INTERVAL = 'interval';
    private const OPTION_ALL = 'all';
    private const OPTION_PROJECTS = 'projects';
    private const OPTION_VERSIONS = 'versions';
    private const OPTION_ISSUES = 'issues';
    private const OPTION_WORKLOGS = 'worklogs';
    private const OPTION_WORKERS = 'workers';
    private const OPTION_DISABLE_MODIFIED_AT_CHECK = 'disable-modified-at-check';

    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
        private readonly string $monitoringUrl,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->getName());
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(self::OPTION_JOB, 'j', InputOption::VALUE_NONE, 'Use async job handling');
        $this->addOption(self::OPTION_INTERVAL, null, InputOption::VALUE_OPTIONAL, 'Only consider items modified within the specified interval up to now. See https://www.php.net/manual/en/dateinterval.construct.php for format.');
        $this->addOption(self::OPTION_ALL, 'a', InputOption::VALUE_NONE, 'Sync all');
        $this->addOption(self::OPTION_PROJECTS, 'p', InputOption::VALUE_NONE, 'Sync projects');
        $this->addOption(self::OPTION_VERSIONS, 's', InputOption::VALUE_NONE, 'Sync versions');
        $this->addOption(self::OPTION_ISSUES, 'i', InputOption::VALUE_NONE, 'Sync issues');
        $this->addOption(self::OPTION_WORKLOGS, 'w', InputOption::VALUE_NONE, 'Sync worklogs');
        $this->addOption(self::OPTION_WORKERS, 'r', InputOption::VALUE_NONE, 'Sync workers');
        $this->addOption(self::OPTION_DISABLE_MODIFIED_AT_CHECK, 'd', InputOption::VALUE_NONE, 'Disable modifiedAt check. This will synchronize all items even though item.modifiedAt has not changed.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jobHandling = $input->getOption(self::OPTION_JOB);
        $disableModifiedAtCheck = $input->getOption(self::OPTION_DISABLE_MODIFIED_AT_CHECK);
        $interval = $input->getOption(self::OPTION_INTERVAL);
        $modifiedAfter = null;

        if (!empty($interval)) {
            try {
                $modifiedAfter = (new \DateTime())->sub(new \DateInterval($interval));
            } catch (\Exception $e) {
                $io->error('Error parsing interval option: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        $io->info('Handle as jobs: '.($jobHandling ? 'TRUE' : 'FALSE'));
        $io->info('Ignore modified at checks: '.($disableModifiedAtCheck ? 'TRUE' : 'FALSE'));

        null !== $modifiedAfter && $io->info('Only handle items modified since: '.$modifiedAfter->format('Y-m-d H:i:s'));

        if ($input->getOption(self::OPTION_ALL)) {
            $io->info('Syncing all.');
            $this->leantimeApiService->updateAll($jobHandling, $modifiedAfter, $disableModifiedAtCheck);

            return Command::SUCCESS;
        }

        if ($input->getOption(self::OPTION_PROJECTS)) {
            $io->info('Syncing projects.');
            $this->leantimeApiService->update(Project::class, $jobHandling, $modifiedAfter, $disableModifiedAtCheck);
        }

        if ($input->getOption(self::OPTION_VERSIONS)) {
            $io->info('Syncing versions.');
            $this->leantimeApiService->update(Version::class, $jobHandling, $modifiedAfter, $disableModifiedAtCheck);
        }

        if ($input->getOption(self::OPTION_ISSUES)) {
            $io->info('Syncing issues.');
            $this->leantimeApiService->update(Issue::class, $jobHandling, $modifiedAfter, $disableModifiedAtCheck);
        }

        if ($input->getOption(self::OPTION_WORKLOGS)) {
            $io->info('Syncing worklogs.');
            $this->leantimeApiService->update(Worklog::class, $jobHandling, $modifiedAfter, $disableModifiedAtCheck);
        }

        if ($input->getOption(self::OPTION_WORKERS)) {
            $io->info('Syncing workers.');
            $this->leantimeApiService->update(Worker::class, $jobHandling, $modifiedAfter, $disableModifiedAtCheck);
        }

        // Call monitoring url if defined.
        if ('' !== $this->monitoringUrl) {
            try {
                $this->client->request('GET', $this->monitoringUrl);
            } catch (\Throwable $e) {
                $this->logger->error('Error calling monitoringUrl: '.$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
