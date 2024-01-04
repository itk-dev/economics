<?php

namespace App\Command;

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
        private readonly DataProviderService $dataProviderService,
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $io->ask('Name');
        $url = $io->ask('URL');
        $secret = $io->ask('Secret');
        $question = new Question('Implementation class');
        $question->setAutocompleterValues(DataProviderService::IMPLEMENTATIONS);
        $class = $io->askQuestion($question);

        $dataProvider = $this->dataProviderService->createDataProvider($name, $class, $url, $secret, false, false);

        $text = "Created the following data provider\n\n";
        $text .= 'ID: '.$dataProvider->getId()."\n";
        $text .= 'Name: '.$dataProvider->getName()."\n";
        $text .= 'URL: '.$dataProvider->getUrl()."\n";
        $text .= "Secret: ****\n";
        $text .= 'Class: '.$dataProvider->getClass()."\n";
        $text .= 'Enable client sync: '.$dataProvider->isEnableClientSync()."\n";
        $text .= 'Enable account sync: '.$dataProvider->isEnableAccountSync()."\n";

        $io->info($text);

        return Command::SUCCESS;
    }
}
