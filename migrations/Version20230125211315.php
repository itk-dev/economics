<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230125211315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog ADD description VARCHAR(255) DEFAULT NULL, ADD worker VARCHAR(255) NOT NULL, ADD time_spent_seconds INT NOT NULL, ADD started DATETIME NOT NULL, ADD issue_name VARCHAR(255) NOT NULL, ADD project_tracker_issue_id VARCHAR(255) NOT NULL, ADD project_tracker_issue_key VARCHAR(255) NOT NULL, ADD epic_name VARCHAR(255) DEFAULT NULL, ADD epic_key VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog DROP description, DROP worker, DROP time_spent_seconds, DROP started, DROP issue_name, DROP project_tracker_issue_id, DROP project_tracker_issue_key, DROP epic_name, DROP epic_key');
    }
}
