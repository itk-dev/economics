<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240708140922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX data_provider_project_tracker ON issue (data_provider_id, project_tracker_id)');
        $this->addSql('CREATE UNIQUE INDEX data_provider_project_tracker ON project (data_provider_id, project_tracker_id)');
        $this->addSql('CREATE UNIQUE INDEX data_provider_project_tracker ON version (data_provider_id, project_tracker_id)');
        $this->addSql('DELETE FROM worklog WHERE deleted_at IS NOT NULL');
        $this->addSql('ALTER TABLE worklog DROP deleted_at');
        $this->addSql('CREATE UNIQUE INDEX data_provider_project_tracker ON worklog (data_provider_id, worklog_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX data_provider_project_tracker ON version');
        $this->addSql('DROP INDEX data_provider_project_tracker ON project');
        $this->addSql('DROP INDEX data_provider_project_tracker ON worklog');
        $this->addSql('ALTER TABLE worklog ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('DROP INDEX data_provider_project_tracker ON issue');
    }
}
