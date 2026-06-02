<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517151632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make ManyToOne foreign keys nullable on entities whose properties are typed nullable. Aligns DB schema with PHP property types; application/validation layer still enforces required-ness on app-managed entities.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cybersecurity_agreement CHANGE service_agreement_id service_agreement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_entry CHANGE invoice_id invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE issue_product CHANGE issue_id issue_id INT DEFAULT NULL, CHANGE product_id product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project_billing CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement CHANGE project_id project_id INT DEFAULT NULL, CHANGE client_id client_id INT DEFAULT NULL, CHANGE project_lead_id project_lead_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE version CHANGE project_id project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog CHANGE issue_id issue_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice_entry CHANGE invoice_id invoice_id INT NOT NULL');
        $this->addSql('ALTER TABLE worklog CHANGE issue_id issue_id INT NOT NULL');
        $this->addSql('ALTER TABLE product CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE issue_product CHANGE issue_id issue_id INT NOT NULL, CHANGE product_id product_id INT NOT NULL');
        $this->addSql('ALTER TABLE project_billing CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE version CHANGE project_id project_id INT NOT NULL');
        $this->addSql('ALTER TABLE service_agreement CHANGE project_id project_id INT NOT NULL, CHANGE client_id client_id INT NOT NULL, CHANGE project_lead_id project_lead_id INT NOT NULL');
        $this->addSql('ALTER TABLE cybersecurity_agreement CHANGE service_agreement_id service_agreement_id INT NOT NULL');
    }
}
