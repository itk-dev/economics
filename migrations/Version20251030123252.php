<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030123252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cybersecurity_agreement (id INT AUTO_INCREMENT NOT NULL, service_agreement_id INT NOT NULL, quarterly_hours DOUBLE PRECISION DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, INDEX IDX_E8FE12D3FB257ECE (service_agreement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_agreement (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, client_id INT NOT NULL, cybersecurity_agreement_id INT DEFAULT NULL, project_lead_id INT NOT NULL, hosting_provider VARCHAR(255) NOT NULL, document_url VARCHAR(255) DEFAULT NULL, price DOUBLE PRECISION NOT NULL, valid_from DATETIME NOT NULL, valid_to DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, system_owner_notice VARCHAR(255) NOT NULL, INDEX IDX_76F4606E166D1F9C (project_id), INDEX IDX_76F4606E19EB6921 (client_id), INDEX IDX_76F4606ECB7FE179 (cybersecurity_agreement_id), INDEX IDX_76F4606E964B49E8 (project_lead_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cybersecurity_agreement ADD CONSTRAINT FK_E8FE12D3FB257ECE FOREIGN KEY (service_agreement_id) REFERENCES service_agreement (id)');
        $this->addSql('ALTER TABLE service_agreement ADD CONSTRAINT FK_76F4606E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE service_agreement ADD CONSTRAINT FK_76F4606E19EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE service_agreement ADD CONSTRAINT FK_76F4606ECB7FE179 FOREIGN KEY (cybersecurity_agreement_id) REFERENCES cybersecurity_agreement (id)');
        $this->addSql('ALTER TABLE service_agreement ADD CONSTRAINT FK_76F4606E964B49E8 FOREIGN KEY (project_lead_id) REFERENCES worker (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cybersecurity_agreement DROP FOREIGN KEY FK_E8FE12D3FB257ECE');
        $this->addSql('ALTER TABLE service_agreement DROP FOREIGN KEY FK_76F4606E166D1F9C');
        $this->addSql('ALTER TABLE service_agreement DROP FOREIGN KEY FK_76F4606E19EB6921');
        $this->addSql('ALTER TABLE service_agreement DROP FOREIGN KEY FK_76F4606ECB7FE179');
        $this->addSql('ALTER TABLE service_agreement DROP FOREIGN KEY FK_76F4606E964B49E8');
        $this->addSql('DROP TABLE cybersecurity_agreement');
        $this->addSql('DROP TABLE service_agreement');
    }
}
