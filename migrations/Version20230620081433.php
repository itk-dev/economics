<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230620081433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE issue (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, account_key VARCHAR(255) DEFAULT NULL, account_id VARCHAR(255) DEFAULT NULL, project_tracker_id VARCHAR(255) NOT NULL, project_tracker_key VARCHAR(255) NOT NULL, epic_key VARCHAR(255) DEFAULT NULL, epic_name VARCHAR(255) DEFAULT NULL, resolution_date DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE issue_version (issue_id INT NOT NULL, version_id INT NOT NULL, INDEX IDX_1AE496225E7AA58C (issue_id), INDEX IDX_1AE496224BBC2705 (version_id), PRIMARY KEY(issue_id, version_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE issue_version ADD CONSTRAINT FK_1AE496225E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue_version ADD CONSTRAINT FK_1AE496224BBC2705 FOREIGN KEY (version_id) REFERENCES version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A48A4CA35');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A4BBC2705');
        $this->addSql('DROP TABLE version_worklog');
        $this->addSql('ALTER TABLE project ADD include TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE project_billing ADD recorded TINYINT(1) NOT NULL, ADD description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog ADD issue_id INT NOT NULL, DROP issue_name, DROP project_tracker_issue_id, DROP project_tracker_issue_key, DROP epic_name, DROP epic_key, CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2E5E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id)');
        $this->addSql('CREATE INDEX IDX_524AFE2E5E7AA58C ON worklog (issue_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2E5E7AA58C');
        $this->addSql('CREATE TABLE version_worklog (version_id INT NOT NULL, worklog_id INT NOT NULL, INDEX IDX_AE831B7A48A4CA35 (worklog_id), INDEX IDX_AE831B7A4BBC2705 (version_id), PRIMARY KEY(version_id, worklog_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A48A4CA35 FOREIGN KEY (worklog_id) REFERENCES worklog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A4BBC2705 FOREIGN KEY (version_id) REFERENCES version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue_version DROP FOREIGN KEY FK_1AE496225E7AA58C');
        $this->addSql('ALTER TABLE issue_version DROP FOREIGN KEY FK_1AE496224BBC2705');
        $this->addSql('DROP TABLE issue');
        $this->addSql('DROP TABLE issue_version');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX IDX_524AFE2E5E7AA58C ON worklog');
        $this->addSql('ALTER TABLE worklog ADD issue_name VARCHAR(255) NOT NULL, ADD project_tracker_issue_id VARCHAR(255) NOT NULL, ADD project_tracker_issue_key VARCHAR(255) NOT NULL, ADD epic_name VARCHAR(255) DEFAULT NULL, ADD epic_key VARCHAR(255) DEFAULT NULL, DROP issue_id, CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project DROP include');
        $this->addSql('ALTER TABLE project_billing DROP recorded, DROP description');
    }
}
