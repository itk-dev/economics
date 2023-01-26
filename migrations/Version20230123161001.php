<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230123161001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice_entry ADD receiver_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_entry ADD CONSTRAINT FK_16FBCDC5D8CF5973 FOREIGN KEY (receiver_account_id) REFERENCES account (id)');
        $this->addSql('CREATE INDEX IDX_16FBCDC5D8CF5973 ON invoice_entry (receiver_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice_entry DROP FOREIGN KEY FK_16FBCDC5D8CF5973');
        $this->addSql('DROP INDEX IDX_16FBCDC5D8CF5973 ON invoice_entry');
        $this->addSql('ALTER TABLE invoice_entry DROP receiver_account_id');
    }
}
