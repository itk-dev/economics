<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240530115938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue_product ADD invoice_entry_id INT DEFAULT NULL, ADD is_billed TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE issue_product ADD CONSTRAINT FK_76B2414CA51E131A FOREIGN KEY (invoice_entry_id) REFERENCES invoice_entry (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_76B2414CA51E131A ON issue_product (invoice_entry_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue_product DROP FOREIGN KEY FK_76B2414CA51E131A');
        $this->addSql('DROP INDEX IDX_76B2414CA51E131A ON issue_product');
        $this->addSql('ALTER TABLE issue_product DROP invoice_entry_id, DROP is_billed');
    }
}
