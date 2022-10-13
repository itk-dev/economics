<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221013140141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense (id INT AUTO_INCREMENT NOT NULL, invoice_entry_id INT DEFAULT NULL, is_billed TINYINT(1) NOT NULL, expense_id INT NOT NULL, INDEX IDX_2D3A8DA6A51E131A (invoice_entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, recorded TINYINT(1) NOT NULL, customer_account_id INT DEFAULT NULL, recorded_date DATETIME DEFAULT NULL, exported_date DATETIME DEFAULT NULL, locked_contact_name VARCHAR(255) DEFAULT NULL, locked_type VARCHAR(255) DEFAULT NULL, locked_account_key VARCHAR(255) DEFAULT NULL, locked_sales_channel VARCHAR(255) DEFAULT NULL, paid_by_account VARCHAR(255) DEFAULT NULL, default_pay_to_account VARCHAR(255) DEFAULT NULL, default_material_number VARCHAR(255) DEFAULT NULL, period_from DATETIME DEFAULT NULL, period_to DATETIME DEFAULT NULL, INDEX IDX_90651744166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice_entry (id INT AUTO_INCREMENT NOT NULL, invoice_id INT NOT NULL, description VARCHAR(255) DEFAULT NULL, account VARCHAR(255) DEFAULT NULL, product VARCHAR(255) DEFAULT NULL, price INT DEFAULT NULL, amount INT DEFAULT NULL, entry_type VARCHAR(255) NOT NULL, material_number VARCHAR(255) DEFAULT NULL, INDEX IDX_16FBCDC52989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, remote_key VARCHAR(255) NOT NULL, remote_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worklog (id INT AUTO_INCREMENT NOT NULL, invoice_entry_id INT NOT NULL, worklog_id INT NOT NULL, is_billed TINYINT(1) NOT NULL, INDEX IDX_524AFE2EA51E131A (invoice_entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6A51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice_entry ADD CONSTRAINT FK_16FBCDC52989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6A51E131A');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744166D1F9C');
        $this->addSql('ALTER TABLE invoice_entry DROP FOREIGN KEY FK_16FBCDC52989F1FD');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_entry');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE worklog');
    }
}
