<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend service_agreement with new fields, change system_owner_notice to JSON, make valid_to nullable, add timestamps';
    }

    public function up(Schema $schema): void
    {
        // Change system_owner_notice from VARCHAR enum to JSON array (reset all to empty)
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN system_owner_notice');
        $this->addSql('ALTER TABLE service_agreement ADD system_owner_notices JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('UPDATE service_agreement SET system_owner_notices = \'[]\' WHERE system_owner_notices IS NULL');

        // Make valid_to nullable
        $this->addSql('ALTER TABLE service_agreement CHANGE valid_to valid_to DATETIME DEFAULT NULL');

        // Add new fields
        $this->addSql('ALTER TABLE service_agreement ADD is_eol TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE service_agreement ADD leantime_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD client_contact_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD client_contact_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD dedicated_server TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE service_agreement ADD server_size VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD cybersecurity_price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD git_repos LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE service_agreement ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        // Remove new fields
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN is_eol');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN leantime_url');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN client_contact_name');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN client_contact_email');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN dedicated_server');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN server_size');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN cybersecurity_price');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN git_repos');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN created_at');
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN updated_at');

        // Revert valid_to to NOT NULL
        $this->addSql('ALTER TABLE service_agreement CHANGE valid_to valid_to DATETIME NOT NULL');

        // Revert system_owner_notices JSON back to system_owner_notice VARCHAR
        $this->addSql('ALTER TABLE service_agreement DROP COLUMN system_owner_notices');
        $this->addSql('ALTER TABLE service_agreement ADD system_owner_notice VARCHAR(255) NOT NULL DEFAULT \'never\'');
    }
}
