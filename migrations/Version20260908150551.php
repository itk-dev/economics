<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908150551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the single client contact on a service agreement with a contact list carrying role tags, migrating the existing name and email across.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_contact_role_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_agreement_contact (id INT AUTO_INCREMENT NOT NULL, service_agreement_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_8E6CB749FB257ECE (service_agreement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE service_agreement_contact_contact_role (service_agreement_contact_id INT NOT NULL, contact_role_id INT NOT NULL, INDEX IDX_BDA17C59F68A66FD (service_agreement_contact_id), INDEX IDX_BDA17C594C2C032D (contact_role_id), PRIMARY KEY(service_agreement_contact_id, contact_role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE service_agreement_contact ADD CONSTRAINT FK_8E6CB749FB257ECE FOREIGN KEY (service_agreement_id) REFERENCES service_agreement (id)');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role ADD CONSTRAINT FK_BDA17C59F68A66FD FOREIGN KEY (service_agreement_contact_id) REFERENCES service_agreement_contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role ADD CONSTRAINT FK_BDA17C594C2C032D FOREIGN KEY (contact_role_id) REFERENCES contact_role (id) ON DELETE CASCADE');

        // Carry the single contact each agreement could hold over into the new
        // list before the columns go. No role is attached: the old columns never
        // recorded one.
        $this->addSql('INSERT INTO service_agreement_contact (service_agreement_id, name, email, created_at, updated_at) SELECT id, client_contact_name, client_contact_email, NOW(), NOW() FROM service_agreement WHERE client_contact_name IS NOT NULL OR client_contact_email IS NOT NULL');

        $this->addSql('ALTER TABLE service_agreement DROP client_contact_name, DROP client_contact_email');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_agreement ADD client_contact_name VARCHAR(255) DEFAULT NULL, ADD client_contact_email VARCHAR(255) DEFAULT NULL');

        // Reversed before the tables go, and only the oldest contact per
        // agreement fits back into the two columns.
        $this->addSql('UPDATE service_agreement sa INNER JOIN (SELECT service_agreement_id, MIN(id) AS id FROM service_agreement_contact GROUP BY service_agreement_id) oldest ON oldest.service_agreement_id = sa.id INNER JOIN service_agreement_contact sac ON sac.id = oldest.id SET sa.client_contact_name = sac.name, sa.client_contact_email = sac.email');

        $this->addSql('ALTER TABLE service_agreement_contact DROP FOREIGN KEY FK_8E6CB749FB257ECE');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role DROP FOREIGN KEY FK_BDA17C59F68A66FD');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role DROP FOREIGN KEY FK_BDA17C594C2C032D');
        $this->addSql('DROP TABLE service_agreement_contact_contact_role');
        $this->addSql('DROP TABLE service_agreement_contact');
        $this->addSql('DROP TABLE contact_role');
    }
}
