<?php

namespace App\Command;

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
        $this->addOption("job", 'j', InputOption::VALUE_NONE, "Use async job handling");
        $this->addOption("modified", "m", InputOption::VALUE_NONE, "Only items modified since last update");
        $this->addOption("all", "a", InputOption::VALUE_NONE, "Sync all");
        $this->addOption("projects", "p", InputOption::VALUE_NONE, "Sync projects");
        $this->addOption("versions", "s", InputOption::VALUE_NONE, "Sync versions");
        $this->addOption('issues', "i", InputOption::VALUE_NONE, "Sync issues");
        $this->addOption('worklogs', "w", InputOption::VALUE_NONE, "Sync worklogs");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jobHandling = $input->getOption("job");
        $modified = $input->getOption("modified");

        $io->info("Handle as jobs: " . ($jobHandling ? 'TRUE' : 'FALSE'));
        $io->info("Only handle items modified since last update: " . ($modified ? 'TRUE' : 'FALSE'));

        if ($input->getOption('all')) {
            $io->info("Syncing all.");
            $this->leantimeApiService->updateAll($jobHandling, $modified);
            return Command::SUCCESS;
        }

        if ($input->getOption("projects")) {
            $io->info("Syncing projects.");
            $this->leantimeApiService->updateProjects($jobHandling, $modified);
        }

        if ($input->getOption("versions")) {
            $io->info("Syncing versions.");
            $this->leantimeApiService->updateVersions($jobHandling, $modified);
        }

        if ($input->getOption("issues")) {
            $io->info("Syncing issues.");
            $this->leantimeApiService->updateIssues($jobHandling, $modified);
        }

        if ($input->getOption("worklogs")) {
            $io->info("Syncing worklogs.");
            $this->leantimeApiService->updateWorklogs($jobHandling, $modified);
        }

        return Command::SUCCESS;
    }
}
