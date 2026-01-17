<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106084310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE synchronization_job');
        $this->addSql('ALTER TABLE issue ADD fetch_date DATETIME DEFAULT NULL, ADD source_modified_date DATETIME DEFAULT NULL, ADD source_deleted_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD fetch_date DATETIME DEFAULT NULL, ADD source_modified_date DATETIME DEFAULT NULL, ADD source_deleted_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE version ADD fetch_date DATETIME DEFAULT NULL, ADD source_modified_date DATETIME DEFAULT NULL, ADD source_deleted_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE worklog ADD fetch_date DATETIME DEFAULT NULL, ADD source_modified_date DATETIME DEFAULT NULL, ADD source_deleted_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE synchronization_job (id INT AUTO_INCREMENT NOT NULL, started DATETIME DEFAULT NULL, ended DATETIME DEFAULT NULL, progress INT DEFAULT NULL, step VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, messages LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE project DROP fetch_date, DROP source_modified_date, DROP source_deleted_date');
        $this->addSql('ALTER TABLE worklog DROP fetch_date, DROP source_modified_date, DROP source_deleted_date');
        $this->addSql('ALTER TABLE version DROP fetch_date, DROP source_modified_date, DROP source_deleted_date');
        $this->addSql('ALTER TABLE issue DROP fetch_date, DROP source_modified_date, DROP source_deleted_date');
    }
}
