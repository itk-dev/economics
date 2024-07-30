<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240729132206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate legacy string values to strings that matches enum case defined in IssueStatusEnum.php';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE issue SET status =
            CASE
                WHEN status = 'Lukket' THEN 'done'
                WHEN status = 'Åben' THEN 'new'
                WHEN status = 'Afventer' THEN 'waiting'
                WHEN status = 'I gang' THEN 'in progress'
                WHEN status = 'Til test' THEN 'ready for test'
                WHEN status = 'Klar til planlægning' THEN 'ready for planning'
                WHEN status = 'Klar til release' THEN 'ready for release'
                WHEN status = 'Til review' THEN 'in review'
                WHEN status = 'Done' THEN 'done'
                WHEN status = 'To Do' THEN 'new'
                WHEN status = 'In Progress' THEN 'in progress'
                WHEN status = 'Closed' THEN 'done'
            END
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE issue SET status =
            CASE
                WHEN status = 'done' THEN 'Lukket'
                WHEN status = 'new' THEN 'Åben'
                WHEN status = 'waiting' THEN 'Afventer'
                WHEN status = 'in progress' THEN 'I gang'
                WHEN status = 'ready for test' THEN 'Til test'
                WHEN status = 'ready for planning' THEN 'Klar til planlægning'
                WHEN status = 'ready for release' THEN 'Klar til release'
                WHEN status = 'in review' THEN 'Til review'
                WHEN status = 'done' THEN 'Done'
                WHEN status = 'new' THEN 'To Do'
                WHEN status = 'in progress' THEN 'In Progress'
                WHEN status = 'done' THEN 'Closed'
            END
        ");
    }
}
