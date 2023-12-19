<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use App\Service\JiraApiService;
use App\Service\LeantimeApiService;
use App\Service\DataProviderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:project-tracker:create',
    description: 'Create a new Project Tracker',
)]
class DataProviderCreateCommand extends Command
{
    public function __construct(
        private readonly DataProviderService $projectTrackerService,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $io->ask("Name");
        $url = $io->ask("URL");
        $basicAuth = $io->ask("Basic Auth");
        $question = new Question('Implementation class');
        $question->setAutocompleterValues(DataProviderService::IMPLEMENTATIONS);
        $class = $io->askQuestion($question);

        $this->projectTrackerService->createProjectTracker($name, $class, $url, $basicAuth);

        return Command::SUCCESS;
    }
}
