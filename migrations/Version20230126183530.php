<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230126183530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EA51E131A');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id)');
    }
}
