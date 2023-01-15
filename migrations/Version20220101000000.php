<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration from Previous System.
 */
final class Version20220101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE debtor (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, label VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE debtor_user (debtor_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_CB4B6CA3B043EC6B (debtor_id), INDEX IDX_CB4B6CA3A76ED395 (user_id), PRIMARY KEY(debtor_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE expense (id INT AUTO_INCREMENT NOT NULL, invoice_entry_id INT NOT NULL, is_billed TINYINT(1) DEFAULT NULL, expense_id INT NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_2D3A8DA6A51E131A (invoice_entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE expense_category (id INT NOT NULL, name VARCHAR(255) NOT NULL, unit_price NUMERIC(16, 4) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fos_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, username_canonical VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, email_canonical VARCHAR(180) NOT NULL, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', portal_apps LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', full_name VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, postal_code INT DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, no_default_settings TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_957A647992FC23A8 (username_canonical), UNIQUE INDEX UNIQ_957A6479A0D96FBF (email_canonical), UNIQUE INDEX UNIQ_957A6479C05FB297 (confirmation_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gs_order (id INT AUTO_INCREMENT NOT NULL, issue_id INT NOT NULL, issue_key VARCHAR(255) NOT NULL, full_name VARCHAR(255) DEFAULT NULL, job_title VARCHAR(255) DEFAULT NULL, order_lines LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', description LONGTEXT DEFAULT NULL, files LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', debitor INT DEFAULT NULL, marketing_account VARCHAR(255) DEFAULT NULL, library VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, postalcode INT DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, date DATE DEFAULT NULL, delivery_description LONGTEXT DEFAULT NULL, own_cloud_shared_files LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', order_status VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, recorded TINYINT(1) NOT NULL, customer_account_id INT DEFAULT NULL, recorded_date DATETIME DEFAULT NULL, exported_date DATETIME DEFAULT NULL, locked_customer_key VARCHAR(255) DEFAULT NULL, locked_contact_name VARCHAR(255) DEFAULT NULL, locked_type VARCHAR(255) DEFAULT NULL, locked_account_key VARCHAR(255) DEFAULT NULL, locked_sales_channel VARCHAR(255) DEFAULT NULL, paid_by_account VARCHAR(255) DEFAULT NULL, default_pay_to_account VARCHAR(255) DEFAULT NULL, default_material_number VARCHAR(255) DEFAULT NULL, period_from DATETIME DEFAULT NULL, period_to DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_90651744166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice_entry (id INT AUTO_INCREMENT NOT NULL, invoice_id INT NOT NULL, description VARCHAR(255) DEFAULT NULL, account VARCHAR(255) DEFAULT NULL, product VARCHAR(255) DEFAULT NULL, price NUMERIC(10, 2) DEFAULT NULL, amount NUMERIC(10, 2) DEFAULT NULL, entry_type VARCHAR(255) NOT NULL, material_number VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_16FBCDC52989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, jira_key VARCHAR(255) NOT NULL, jira_id INT NOT NULL, avatar_url VARCHAR(255) NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worklog (id INT AUTO_INCREMENT NOT NULL, invoice_entry_id INT NOT NULL, worklog_id INT NOT NULL, is_billed TINYINT(1) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_524AFE2EA51E131A (invoice_entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE debtor_user ADD CONSTRAINT FK_CB4B6CA3B043EC6B FOREIGN KEY (debtor_id) REFERENCES debtor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE debtor_user ADD CONSTRAINT FK_CB4B6CA3A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6A51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice_entry ADD CONSTRAINT FK_16FBCDC52989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE debtor_user DROP FOREIGN KEY FK_CB4B6CA3B043EC6B');
        $this->addSql('ALTER TABLE debtor_user DROP FOREIGN KEY FK_CB4B6CA3A76ED395');
        $this->addSql('ALTER TABLE invoice_entry DROP FOREIGN KEY FK_16FBCDC52989F1FD');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6A51E131A');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744166D1F9C');
        $this->addSql('DROP TABLE debtor');
        $this->addSql('DROP TABLE debtor_user');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE expense_category');
        $this->addSql('DROP TABLE fos_user');
        $this->addSql('DROP TABLE gs_order');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_entry');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE worklog');
    }
}
