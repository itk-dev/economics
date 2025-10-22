<?php

namespace App\Command;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Service\LeantimeApiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:data-providers:sync',
    description: 'Sync Data Provider data',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly LeantimeApiService $leantimeApiService,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
        $this->addOption('job', 'j', InputOption::VALUE_NONE, 'Use async job handling');
        $this->addOption('modified', null, InputOption::VALUE_OPTIONAL, 'Only update items modified since this datetime string (valid formats: https://www.php.net/manual/en/datetime.formats.php)');
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Sync all');
        $this->addOption('projects', 'p', InputOption::VALUE_NONE, 'Sync projects');
        $this->addOption('versions', 's', InputOption::VALUE_NONE, 'Sync versions');
        $this->addOption('issues', 'i', InputOption::VALUE_NONE, 'Sync issues');
        $this->addOption('worklogs', 'w', InputOption::VALUE_NONE, 'Sync worklogs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jobHandling = $input->getOption('job');
        $modified = $input->getOption('modified');

        try {
            $modifiedAfter = $modified !== false ? new \DateTime($modified) : null;
        } catch (\Exception $e) {
            $io->error("Error parsing modified option: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->info('Handle as jobs: '.($jobHandling ? 'TRUE' : 'FALSE'));

        $modifiedAfter !== null && $io->info('Only handle items modified since: '.$modifiedAfter->format('Y-m-d H:i:s') ?? '');

        if ($input->getOption('all')) {
            $io->info('Syncing all.');
            $this->leantimeApiService->updateAll($jobHandling, $modifiedAfter);

            return Command::SUCCESS;
        }

        if ($input->getOption('projects')) {
            $io->info('Syncing projects.');
            $this->leantimeApiService->update(Project::class, $jobHandling, $modifiedAfter);
        }

        if ($input->getOption('versions')) {
            $io->info('Syncing versions.');
            $this->leantimeApiService->update(Version::class, $jobHandling, $modifiedAfter);
        }

        if ($input->getOption('issues')) {
            $io->info('Syncing issues.');
            $this->leantimeApiService->update(Issue::class, $jobHandling, $modifiedAfter);
        }

        if ($input->getOption('worklogs')) {
            $io->info('Syncing worklogs.');
            $this->leantimeApiService->update(Worklog::class, $jobHandling, $modifiedAfter);
        }

        return Command::SUCCESS;
    }
}
