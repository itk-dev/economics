<?php

namespace App\Tests\Unit\Command;

use App\Command\MigrateFromJiraEconomicsCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command issues destructive DDL, so the guard in front of it matters more
 * than the migration itself.
 */
class MigrateFromJiraEconomicsCommandTest extends TestCase
{
    /** @var string[] */
    private array $preparedSql = [];

    private Connection&\PHPUnit\Framework\MockObject\MockObject $connection;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->preparedSql = [];

        $statement = $this->createMock(Statement::class);
        $statement->method('executeQuery')->willReturn($this->createMock(Result::class));

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('prepare')->willReturnCallback(
            function (string $sql) use ($statement): Statement {
                $this->preparedSql[] = $sql;

                return $statement;
            }
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);

        $this->tester = new CommandTester(new MigrateFromJiraEconomicsCommand($entityManager));
    }

    public function testDecliningTheConfirmationRunsNoStatements(): void
    {
        $this->tester->setInputs(['no']);
        $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $this->assertStringContainsString('Aborted', $this->tester->getDisplay());
        $this->assertSame([], $this->preparedSql);
    }

    public function testConfirmationDefaultsToNo(): void
    {
        $this->tester->setInputs(['']);
        $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $this->assertSame([], $this->preparedSql, 'An empty answer must not run destructive DDL.');
    }

    public function testConfirmingRunsTheMigrationStatements(): void
    {
        $this->tester->setInputs(['yes']);
        $this->tester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        $this->assertCount(3, $this->preparedSql);
    }

    public function testMigrationCreatesTheVersionsTableAndSeedsTheFirstMigration(): void
    {
        $this->tester->setInputs(['yes']);
        $this->tester->execute([]);

        $this->assertStringContainsString('CREATE TABLE `doctrine_migration_versions`', $this->preparedSql[0]);
        $this->assertStringContainsString('INSERT INTO `doctrine_migration_versions`', $this->preparedSql[1]);
        $this->assertStringContainsString('Version20230101000000', $this->preparedSql[1]);
    }

    public function testMigrationDropsTheLegacyTables(): void
    {
        $this->tester->setInputs(['yes']);
        $this->tester->execute([]);

        $this->assertStringContainsString('DROP TABLE messenger_messages', $this->preparedSql[2]);
        $this->assertStringContainsString('DROP TABLE migration_versions', $this->preparedSql[2]);
    }

    public function testTheUserIsWarnedBeforeConfirming(): void
    {
        $this->tester->setInputs(['no']);
        $this->tester->execute([]);

        $this->assertStringContainsString('Are you sure?', $this->tester->getDisplay());
    }
}
