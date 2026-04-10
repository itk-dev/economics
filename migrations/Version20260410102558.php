<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410102558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_agreement ADD created_by VARCHAR(255) DEFAULT NULL, ADD updated_by VARCHAR(255) DEFAULT NULL, CHANGE system_owner_notices system_owner_notices JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE is_eol is_eol TINYINT(1) NOT NULL, CHANGE dedicated_server dedicated_server TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_agreement DROP created_by, DROP updated_by, CHANGE system_owner_notices system_owner_notices JSON DEFAULT \'[]\' NOT NULL COMMENT \'(DC2Type:json)\', CHANGE is_eol is_eol TINYINT(1) DEFAULT 0 NOT NULL, CHANGE dedicated_server dedicated_server TINYINT(1) DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
