<?php

namespace App\Command;

use App\Entity\View;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-view',
    description: 'Add view',
)]
class AddViewCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ProjectRepository $projectRepository
    ) {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $view = new View();

        $view->setName($io->ask('Name'));
        $view->setDescription($io->ask('Description'));
        $view->setProtected($io->confirm('Prevent altering/deleting in UI'));
        $this->addDataProvider($io, $view);
        $this->addProjects($io, $view);

        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        $this->entityManager->persist($view);

        // actually executes the queries (i.e. the INSERT query)
        $this->entityManager->flush();

        $io->info('Added new protected view');

        return Command::SUCCESS;
    }

    protected function addDataProvider($io, $view): void
    {
        // Whether to change another field after this one.
        $another = true;
        $dataProviderStrings = [];
        $allDataProviders = $this->dataProviderRepository->findAll();

        while ($another) {
            $idAndTitle = [];
            foreach ($allDataProviders as $dataProvider) {
                $idAndTitle[] = $dataProvider->getName().' ('.$dataProvider->getId().')';
            }

            $question = new Question('Select data providers');
            $question->setAutocompleterValues($idAndTitle);
            $dataProviderStrings[] = $io->askQuestion($question);

            $another = $io->confirm(
                'Add more data providers?',
                false
            );
        }

        foreach ($dataProviderStrings as $dataProviderString) {
            preg_match('#\((.*?)\)#', $dataProviderString, $match);
            if (!empty($match)) {
                $dataProvider = $this->dataProviderRepository->find($match[1]);
                $view->addDataProvider($dataProvider);
            }
        }
    }

    protected function addProjects($io, $view): void
    {
        // Whether to change another field after this one.
        $another = true;
        $projectStrings = [];
        $allProjects = $this->projectRepository->findAll();

        while ($another) {
            $idAndTitle = [];
            foreach ($allProjects as $project) {
                $idAndTitle[] = $project->getName().' ('.$project->getId().')';
            }

            $question = new Question('Select project');
            $question->setAutocompleterValues($idAndTitle);
            $projectStrings[] = $io->askQuestion($question);

            $another = $io->confirm(
                'Add more projects?',
                false
            );
        }

        foreach ($projectStrings as $projectString) {
            preg_match('#\((.*?)\)#', $projectString, $match);
            if (!empty($match)) {
                $project = $this->projectRepository->find($match[1]);
                $view->addProject($project);
            }
        }
    }
}
