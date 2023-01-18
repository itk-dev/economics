<?php

namespace App\Command;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-from-jira-economics',
    description: 'Adds the doctrine migration versions table and adds the first migration to it to avoid executing this migration.',
)]
class MigrateFromJiraEconomicsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($this->getName());
    }

    protected function configure(): void
    {
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info("This command will add the doctrine migration versions table and add the first migration to it to avoid executing this migration.");

        if (!$io->confirm("Are you sure?", false)) {
            $io->writeln('Aborted...');
            return Command::SUCCESS;
        }

        $connection = $this->entityManager->getConnection();

        $sql = "CREATE TABLE `doctrine_migration_versions` (`version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL, `executed_at` datetime DEFAULT NULL, `execution_time` int(11) DEFAULT NULL, PRIMARY KEY (`version`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;";

        $prepared = $connection->prepare($sql);
        $prepared->executeQuery();

        $createInitialRow = "INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES ('DoctrineMigrations\\\\Version20230101000000', '2023-01-01 00:00:00', 1);";

        $prepared = $connection->prepare($createInitialRow);
        $prepared->executeQuery();

        return Command::SUCCESS;
    }
}
