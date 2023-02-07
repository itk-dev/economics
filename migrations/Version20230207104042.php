<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230207104042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, project_tracker_id VARCHAR(255) DEFAULT NULL, source VARCHAR(255) NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact VARCHAR(255) DEFAULT NULL, standard_price DOUBLE PRECISION DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, account VARCHAR(255) DEFAULT NULL, psp VARCHAR(255) DEFAULT NULL, ean VARCHAR(255) DEFAULT NULL, project_tracker_id INT NOT NULL, sales_channel VARCHAR(255) DEFAULT NULL, customer_key VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_client (project_id INT NOT NULL, client_id INT NOT NULL, INDEX IDX_D0E0EF1F166D1F9C (project_id), INDEX IDX_D0E0EF1F19EB6921 (client_id), PRIMARY KEY(project_id, client_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_version_budget (id INT AUTO_INCREMENT NOT NULL, project_id VARCHAR(255) NOT NULL, version_id VARCHAR(255) NOT NULL, budget DOUBLE PRECISION NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE version (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, project_tracker_id VARCHAR(255) NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_BF1CD3C3166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE version_worklog (version_id INT NOT NULL, worklog_id INT NOT NULL, INDEX IDX_AE831B7A4BBC2705 (version_id), INDEX IDX_AE831B7A48A4CA35 (worklog_id), PRIMARY KEY(version_id, worklog_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_client ADD CONSTRAINT FK_D0E0EF1F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_client ADD CONSTRAINT FK_D0E0EF1F19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A4BBC2705 FOREIGN KEY (version_id) REFERENCES version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A48A4CA35 FOREIGN KEY (worklog_id) REFERENCES worklog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice ADD client_id INT DEFAULT NULL, ADD total_price DOUBLE PRECISION DEFAULT NULL, CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174419EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('CREATE INDEX IDX_9065174419EB6921 ON invoice (client_id)');
        $this->addSql('ALTER TABLE invoice_entry ADD total_price DOUBLE PRECISION DEFAULT NULL, CHANGE price price DOUBLE PRECISION DEFAULT NULL, CHANGE amount amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('ALTER TABLE worklog ADD project_id INT DEFAULT NULL, ADD description VARCHAR(255) DEFAULT NULL, ADD worker VARCHAR(255) NOT NULL, ADD time_spent_seconds INT NOT NULL, ADD started DATETIME NOT NULL, ADD issue_name VARCHAR(255) NOT NULL, ADD project_tracker_issue_id VARCHAR(255) NOT NULL, ADD project_tracker_issue_key VARCHAR(255) NOT NULL, ADD epic_name VARCHAR(255) DEFAULT NULL, ADD epic_key VARCHAR(255) DEFAULT NULL, ADD source VARCHAR(255) NOT NULL, ADD billed_seconds INT DEFAULT NULL, CHANGE invoice_entry_id invoice_entry_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_524AFE2E166D1F9C ON worklog (project_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174419EB6921');
        $this->addSql('ALTER TABLE project_client DROP FOREIGN KEY FK_D0E0EF1F166D1F9C');
        $this->addSql('ALTER TABLE project_client DROP FOREIGN KEY FK_D0E0EF1F19EB6921');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C3166D1F9C');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A4BBC2705');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A48A4CA35');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE project_client');
        $this->addSql('DROP TABLE project_version_budget');
        $this->addSql('DROP TABLE version');
        $this->addSql('DROP TABLE version_worklog');
        $this->addSql('DROP INDEX IDX_9065174419EB6921 ON invoice');
        $this->addSql('ALTER TABLE invoice DROP client_id, DROP total_price, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2E166D1F9C');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('DROP INDEX IDX_524AFE2E166D1F9C ON worklog');
        $this->addSql('ALTER TABLE worklog DROP project_id, DROP description, DROP worker, DROP time_spent_seconds, DROP started, DROP issue_name, DROP project_tracker_issue_id, DROP project_tracker_issue_key, DROP epic_name, DROP epic_key, DROP source, DROP billed_seconds, CHANGE invoice_entry_id invoice_entry_id INT NOT NULL');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
        $this->addSql('ALTER TABLE invoice_entry DROP total_price, CHANGE price price NUMERIC(10, 2) DEFAULT NULL, CHANGE amount amount NUMERIC(10, 2) DEFAULT NULL');
    }
}
