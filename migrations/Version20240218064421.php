<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240218064421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP status, DROP category');
        $this->addSql('ALTER TABLE client ADD version_name VARCHAR(255) DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, DROP project_lead_name, DROP project_lead_mail, DROP account, DROP sales_channel');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455EF336202 ON client (version_name)');
        $this->addSql('ALTER TABLE invoice CHANGE locked_account_key locked_ean VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD project_lead_name VARCHAR(255) DEFAULT NULL, ADD project_lead_mail VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice CHANGE locked_ean locked_account_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_C7440455EF336202 ON client');
        $this->addSql('ALTER TABLE client ADD project_lead_mail VARCHAR(255) DEFAULT NULL, ADD account VARCHAR(255) DEFAULT NULL, ADD sales_channel VARCHAR(255) DEFAULT NULL, DROP deleted_at, CHANGE version_name project_lead_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD status VARCHAR(255) DEFAULT NULL, ADD category VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project DROP project_lead_name, DROP project_lead_mail');
    }
}
