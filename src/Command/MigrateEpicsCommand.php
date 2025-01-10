<?php

namespace App\Command;

use App\Entity\Epic;
use App\Entity\Issue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:migrate-epics',
    description: 'Migrate epics for existing legacy issues, create the epics and add link to join table.',
)]
class MigrateEpicsCommand extends Command
{
    protected static $defaultName = 'app:assign-epics'; // Default name of the command
    protected static $defaultDescription = 'Assigns Epics to Issues based on the epicName property.';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $issueRepository = $this->entityManager->getRepository(Issue::class);
        $epicRepository = $this->entityManager->getRepository(Epic::class);

        // Get all issues
        $issues = $issueRepository->findAll();
        if (empty($issues)) {
            $output->writeln('<info>No issues found.</info>');

            return Command::SUCCESS;
        }

        foreach ($issues as $issue) {
            $existingEpics = $issue->getEpics()->map(fn ($epic) => trim($epic->getTitle() ?? ''))->toArray();
            $epicNameArray = explode(',', $issue->getEpicName() ?? '');
            foreach ($epicNameArray as $epicName) {
                if (empty($epicName)) {
                    continue;
                }
                $epicName = trim($epicName);

                if (in_array($epicName, $existingEpics, true)) {
                    continue;
                }

                // Check if the Epic exists
                $epic = $epicRepository->findOneBy(['title' => $epicName]);

                if (!$epic) {
                    // Create a new Epic if it doesn't exist
                    $epic = new Epic();
                    $epic->setTitle($epicName);

                    $this->entityManager->persist($epic);
                    $this->entityManager->flush();
                    $output->writeln('<info>Created new Epic: '.$epicName.'</info>');
                }

                // Assign the Epic to the Issue
                $issue->addEpic($epic);

                $this->entityManager->persist($issue);
                $output->writeln('<comment>Assigned Epic "'.$epicName.'" to Issue #'.$issue->getId().'</comment>');
            }
        }

        // Save changes to the database
        $this->entityManager->flush();

        $output->writeln('<info>All issues have been processed successfully.</info>');

        return Command::SUCCESS;
    }
}
