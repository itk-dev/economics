<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251024112807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cybersecurity_agreement (id INT AUTO_INCREMENT NOT NULL, service_agreement_id INT NOT NULL, quarterly_hours DOUBLE PRECISION NOT NULL, note VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_agreement (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, client_id INT NOT NULL, cybersecurity_agreement_id INT DEFAULT NULL, hosting_provider VARCHAR(255) NOT NULL, document_url VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION NOT NULL, project_lead_id INT NOT NULL, valid_from DATETIME NOT NULL, valid_to DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE synchronization_job (id INT AUTO_INCREMENT NOT NULL, started DATETIME DEFAULT NULL, ended DATETIME DEFAULT NULL, progress INT DEFAULT NULL, step VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, messages LONGTEXT DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cybersecurity_agreement');
        $this->addSql('DROP TABLE service_agreement');
        $this->addSql('DROP TABLE synchronization_job');
    }
}
