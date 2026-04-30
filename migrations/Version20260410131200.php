<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410131200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cybersecurity_agreement ADD price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement ADD system_owner_notices JSON NOT NULL COMMENT \'(DC2Type:json)\', ADD is_eol TINYINT(1) NOT NULL, ADD leantime_url VARCHAR(255) DEFAULT NULL, ADD client_contact_name VARCHAR(255) DEFAULT NULL, ADD client_contact_email VARCHAR(255) DEFAULT NULL, ADD dedicated_server TINYINT(1) NOT NULL, ADD server_size VARCHAR(255) DEFAULT NULL, ADD git_repos LONGTEXT DEFAULT NULL, ADD created_by VARCHAR(255) DEFAULT NULL, ADD updated_by VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, DROP system_owner_notice, CHANGE valid_to valid_to DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cybersecurity_agreement DROP price');
        $this->addSql('ALTER TABLE service_agreement ADD system_owner_notice VARCHAR(255) NOT NULL, DROP system_owner_notices, DROP is_eol, DROP leantime_url, DROP client_contact_name, DROP client_contact_email, DROP dedicated_server, DROP server_size, DROP git_repos, DROP created_by, DROP updated_by, DROP created_at, DROP updated_at, CHANGE valid_to valid_to DATETIME NOT NULL');
    }
}
