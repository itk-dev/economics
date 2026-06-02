<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517131038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make columns nullable on synced entities (issue/project/version/worklog) and on app-managed DateTime fields (project_billing period_start/end, service_agreement valid_from) to match PHP property types.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE project_tracker_id project_tracker_id VARCHAR(255) DEFAULT NULL, CHANGE project_tracker_key project_tracker_key VARCHAR(255) DEFAULT NULL, CHANGE link_to_issue link_to_issue VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE project_tracker_project_url project_tracker_project_url VARCHAR(255) DEFAULT NULL, CHANGE project_tracker_key project_tracker_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project_billing CHANGE period_start period_start DATETIME DEFAULT NULL, CHANGE period_end period_end DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement CHANGE valid_from valid_from DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE version CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE project_tracker_id project_tracker_id VARCHAR(255) DEFAULT NULL, CHANGE is_billable is_billable TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog CHANGE worklog_id worklog_id INT DEFAULT NULL, CHANGE worker worker VARCHAR(255) DEFAULT NULL, CHANGE time_spent_seconds time_spent_seconds INT DEFAULT NULL, CHANGE started started DATETIME DEFAULT NULL, CHANGE project_tracker_issue_id project_tracker_issue_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project CHANGE name name VARCHAR(255) NOT NULL, CHANGE project_tracker_project_url project_tracker_project_url VARCHAR(255) NOT NULL, CHANGE project_tracker_key project_tracker_key VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE worklog CHANGE worklog_id worklog_id INT NOT NULL, CHANGE worker worker VARCHAR(255) NOT NULL, CHANGE time_spent_seconds time_spent_seconds INT NOT NULL, CHANGE started started DATETIME NOT NULL, CHANGE project_tracker_issue_id project_tracker_issue_id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project_billing CHANGE period_start period_start DATETIME NOT NULL, CHANGE period_end period_end DATETIME NOT NULL');
        $this->addSql('ALTER TABLE version CHANGE name name VARCHAR(255) NOT NULL, CHANGE project_tracker_id project_tracker_id VARCHAR(255) NOT NULL, CHANGE is_billable is_billable TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE service_agreement CHANGE valid_from valid_from DATETIME NOT NULL');
        $this->addSql('ALTER TABLE issue CHANGE name name VARCHAR(255) NOT NULL, CHANGE project_tracker_id project_tracker_id VARCHAR(255) NOT NULL, CHANGE project_tracker_key project_tracker_key VARCHAR(255) NOT NULL, CHANGE link_to_issue link_to_issue VARCHAR(255) NOT NULL');
    }
}
