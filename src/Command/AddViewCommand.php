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
use Symfony\Component\Console\Question\ChoiceQuestion;
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

        $this->entityManager->persist($view);
        $this->entityManager->flush();

        $io->info('Added new view');

        return Command::SUCCESS;
    }

    protected function addDataProvider($io, $view): void
    {
        $allDataProviders = $this->dataProviderRepository->findAll();

        $idAndTitle = [];
        foreach ($allDataProviders as $dataProvider) {
            $idAndTitle[] = $dataProvider->getName().' ('.$dataProvider->getId().')';
        }

        $question = new ChoiceQuestion(
            'Select dataProviders',
            $idAndTitle
        );
        $question->setMultiselect(true);
        $dataProviderStrings = $io->askQuestion($question);

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
        $allProjects = $this->projectRepository->findAll();

        $idAndTitle = [];
        foreach ($allProjects as $project) {
            $idAndTitle[] = $project->getName().' ('.$project->getId().')';
        }

        $question = new ChoiceQuestion(
            'Select project',
            $idAndTitle
        );
        $question->setMultiselect(true);
        $projectStrings = $io->askQuestion($question);

        foreach ($projectStrings as $projectString) {
            preg_match('#\((.*?)\)#', $projectString, $match);
            if (!empty($match)) {
                $project = $this->projectRepository->find($match[1]);
                $view->addProject($project);
            }
        }
    }
}
