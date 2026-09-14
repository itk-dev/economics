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

        // Copy the single contact each agreement could hold into the new list.
        // No role is attached: the old columns never recorded one.
        //
        // client_contact_name and client_contact_email are deliberately left in
        // place, deprecated rather than dropped, so this migration loses nothing
        // and down() is a clean reversal. A later migration can drop them once
        // the new list is trusted.
        //
        // NULLIF(TRIM(...), '') throughout, because an empty or whitespace-only
        // column is not a contact — matching on IS NOT NULL alone would import
        // it as a blank row that the overview then counts as a contact.
        $this->addSql(<<<'SQL'
            INSERT INTO service_agreement_contact (service_agreement_id, name, email, created_at, updated_at)
            SELECT id,
                   NULLIF(TRIM(client_contact_name), ''),
                   NULLIF(TRIM(client_contact_email), ''),
                   NOW(),
                   NOW()
            FROM service_agreement
            WHERE NULLIF(TRIM(client_contact_name), '') IS NOT NULL
               OR NULLIF(TRIM(client_contact_email), '') IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        //
        // up() only added tables, so this is a clean reversal: the original
        // columns were never touched and still hold what they held. Contacts and
        // roles added since are lost with the tables, which is unavoidable —
        // the two columns cannot represent more than one contact.
        $this->addSql('ALTER TABLE service_agreement_contact DROP FOREIGN KEY FK_8E6CB749FB257ECE');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role DROP FOREIGN KEY FK_BDA17C59F68A66FD');
        $this->addSql('ALTER TABLE service_agreement_contact_contact_role DROP FOREIGN KEY FK_BDA17C594C2C032D');
        $this->addSql('DROP TABLE service_agreement_contact_contact_role');
        $this->addSql('DROP TABLE service_agreement_contact');
        $this->addSql('DROP TABLE contact_role');
    }
}
