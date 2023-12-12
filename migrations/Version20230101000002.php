<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrate to economics entity model.
 */
final class Version20230101000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sets up Economics tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, project_tracker_id VARCHAR(255) DEFAULT NULL, source VARCHAR(255) NOT NULL, status VARCHAR(255) DEFAULT NULL, category VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact VARCHAR(255) DEFAULT NULL, standard_price DOUBLE PRECISION DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, account VARCHAR(255) DEFAULT NULL, psp VARCHAR(255) DEFAULT NULL, ean VARCHAR(255) DEFAULT NULL, project_tracker_id VARCHAR(255) DEFAULT NULL, sales_channel VARCHAR(255) DEFAULT NULL, customer_key VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE issue (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, account_key VARCHAR(255) DEFAULT NULL, account_id VARCHAR(255) DEFAULT NULL, project_tracker_id VARCHAR(255) NOT NULL, project_tracker_key VARCHAR(255) NOT NULL, epic_key VARCHAR(255) DEFAULT NULL, epic_name VARCHAR(255) DEFAULT NULL, resolution_date DATETIME DEFAULT NULL, source VARCHAR(255) NOT NULL, INDEX IDX_12AD233E166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE issue_version (issue_id INT NOT NULL, version_id INT NOT NULL, INDEX IDX_1AE496225E7AA58C (issue_id), INDEX IDX_1AE496224BBC2705 (version_id), PRIMARY KEY(issue_id, version_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_client (project_id INT NOT NULL, client_id INT NOT NULL, INDEX IDX_D0E0EF1F166D1F9C (project_id), INDEX IDX_D0E0EF1F19EB6921 (client_id), PRIMARY KEY(project_id, client_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_billing (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, period_start DATETIME NOT NULL, period_end DATETIME NOT NULL, recorded TINYINT(1) NOT NULL, description LONGTEXT DEFAULT NULL, exported_date DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_7A3C40CF166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project_version_budget (id INT AUTO_INCREMENT NOT NULL, project_id VARCHAR(255) NOT NULL, version_id VARCHAR(255) NOT NULL, budget DOUBLE PRECISION NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE version (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, project_tracker_id VARCHAR(255) NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_BF1CD3C3166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE issue_version ADD CONSTRAINT FK_1AE496225E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue_version ADD CONSTRAINT FK_1AE496224BBC2705 FOREIGN KEY (version_id) REFERENCES version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_client ADD CONSTRAINT FK_D0E0EF1F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_client ADD CONSTRAINT FK_D0E0EF1F19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_billing ADD CONSTRAINT FK_7A3C40CF166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice ADD client_id INT DEFAULT NULL, ADD project_billing_id INT DEFAULT NULL, ADD total_price DOUBLE PRECISION DEFAULT NULL, CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174419EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174423A427B5 FOREIGN KEY (project_billing_id) REFERENCES project_billing (id)');
        $this->addSql('CREATE INDEX IDX_9065174419EB6921 ON invoice (client_id)');
        $this->addSql('CREATE INDEX IDX_9065174423A427B5 ON invoice (project_billing_id)');
        $this->addSql('ALTER TABLE invoice_entry ADD total_price DOUBLE PRECISION DEFAULT NULL, CHANGE price price DOUBLE PRECISION DEFAULT NULL, CHANGE amount amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD include TINYINT(1) DEFAULT NULL, CHANGE project_tracker_id project_tracker_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('ALTER TABLE worklog ADD project_id INT DEFAULT NULL, ADD issue_id INT DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD worker VARCHAR(255) NOT NULL, ADD time_spent_seconds INT NOT NULL, ADD started DATETIME NOT NULL, ADD source VARCHAR(255) NOT NULL, ADD billed_seconds INT DEFAULT NULL, ADD project_tracker_issue_id VARCHAR(255) NOT NULL, CHANGE invoice_entry_id invoice_entry_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2E5E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id)');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_524AFE2E166D1F9C ON worklog (project_id)');
        $this->addSql('CREATE INDEX IDX_524AFE2E5E7AA58C ON worklog (issue_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174419EB6921');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2E5E7AA58C');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174423A427B5');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233E166D1F9C');
        $this->addSql('ALTER TABLE issue_version DROP FOREIGN KEY FK_1AE496225E7AA58C');
        $this->addSql('ALTER TABLE issue_version DROP FOREIGN KEY FK_1AE496224BBC2705');
        $this->addSql('ALTER TABLE project_client DROP FOREIGN KEY FK_D0E0EF1F166D1F9C');
        $this->addSql('ALTER TABLE project_client DROP FOREIGN KEY FK_D0E0EF1F19EB6921');
        $this->addSql('ALTER TABLE project_billing DROP FOREIGN KEY FK_7A3C40CF166D1F9C');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C3166D1F9C');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE issue');
        $this->addSql('DROP TABLE issue_version');
        $this->addSql('DROP TABLE project_client');
        $this->addSql('DROP TABLE project_billing');
        $this->addSql('DROP TABLE project_version_budget');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE version');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX IDX_9065174419EB6921 ON invoice');
        $this->addSql('DROP INDEX IDX_9065174423A427B5 ON invoice');
        $this->addSql('ALTER TABLE invoice DROP client_id, DROP project_billing_id, DROP total_price, CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2E166D1F9C');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('DROP INDEX IDX_524AFE2E166D1F9C ON worklog');
        $this->addSql('DROP INDEX IDX_524AFE2E5E7AA58C ON worklog');
        $this->addSql('ALTER TABLE worklog DROP project_id, DROP issue_id, DROP description, DROP worker, DROP time_spent_seconds, DROP started, DROP source, DROP billed_seconds, DROP project_tracker_issue_id, CHANGE invoice_entry_id invoice_entry_id INT NOT NULL');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
        $this->addSql('ALTER TABLE project DROP include, CHANGE project_tracker_id project_tracker_id INT NOT NULL');
        $this->addSql('ALTER TABLE invoice_entry DROP total_price, CHANGE price price NUMERIC(10, 2) DEFAULT NULL, CHANGE amount amount NUMERIC(10, 2) DEFAULT NULL');
    }
}
