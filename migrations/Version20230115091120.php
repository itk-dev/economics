<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cleanup project table.
 */
final class Version20230115091120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP avatar_url');
        $this->addSql('ALTER TABLE project CHANGE url project_tracker_project_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE jira_key project_tracker_key VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE jira_id project_tracker_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD avatar_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_project_url url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_key jira_key VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_id jira_id INT NOT NULL');
    }
}
