<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove unused tables.
 */
final class Version20230101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes unused tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6A51E131A');
        $this->addSql('ALTER TABLE debtor_user DROP FOREIGN KEY FK_CB4B6CA3A76ED395');
        $this->addSql('ALTER TABLE debtor_user DROP FOREIGN KEY FK_CB4B6CA3B043EC6B');
        $this->addSql('DROP TABLE expense_category');
        $this->addSql('DROP TABLE debtor');
        $this->addSql('DROP TABLE gs_order');
        $this->addSql('DROP TABLE fos_user');
        $this->addSql('DROP TABLE debtor_user');
        $this->addSql('DROP TABLE expense');

        $this->addSql('ALTER TABLE project DROP avatar_url');
        $this->addSql('ALTER TABLE project CHANGE url project_tracker_project_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE jira_key project_tracker_key VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE jira_id project_tracker_id INT NOT NULL');
        $this->addSql('ALTER TABLE invoice CHANGE default_pay_to_account default_receiver_account VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense (id INT AUTO_INCREMENT NOT NULL, invoice_entry_id INT DEFAULT NULL, is_billed TINYINT(1) NOT NULL, expense_id INT NOT NULL, created_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_2D3A8DA6A51E131A (invoice_entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE expense_category (id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, unit_price NUMERIC(16, 4) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE debtor (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, label VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE gs_order (id INT AUTO_INCREMENT NOT NULL, issue_id INT NOT NULL, issue_key VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, full_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, job_title VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, order_lines LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:array)\', description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, files LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:array)\', debitor INT DEFAULT NULL, marketing_account VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, library VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, department VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, phone VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, postalcode INT DEFAULT NULL, city VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, date DATE DEFAULT NULL, delivery_description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, own_cloud_shared_files LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:array)\', order_status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fos_user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, username_canonical VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, email VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, email_canonical VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, last_login DATETIME DEFAULT NULL, confirmation_token VARCHAR(180) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:array)\', portal_apps LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:json)\', full_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, department VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, phone VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, address VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, postal_code INT DEFAULT NULL, city VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, no_default_settings TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_957A647992FC23A8 (username_canonical), UNIQUE INDEX UNIQ_957A6479A0D96FBF (email_canonical), UNIQUE INDEX UNIQ_957A6479C05FB297 (confirmation_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE debtor_user (debtor_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_CB4B6CA3A76ED395 (user_id), INDEX IDX_CB4B6CA3B043EC6B (debtor_id), PRIMARY KEY(debtor_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6A51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
        $this->addSql('ALTER TABLE debtor_user ADD CONSTRAINT FK_CB4B6CA3A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE debtor_user ADD CONSTRAINT FK_CB4B6CA3B043EC6B FOREIGN KEY (debtor_id) REFERENCES debtor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD avatar_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_project_url url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_key jira_key VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project CHANGE project_tracker_id jira_id INT NOT NULL');
        $this->addSql('ALTER TABLE invoice CHANGE default_receiver_account default_pay_to_account VARCHAR(255) DEFAULT NULL');
    }
}
