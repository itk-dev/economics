<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240208110719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP status, DROP category');
        $this->addSql('ALTER TABLE client DROP account, DROP sales_channel');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD account VARCHAR(255) DEFAULT NULL, ADD sales_channel VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD status VARCHAR(255) DEFAULT NULL, ADD category VARCHAR(255) DEFAULT NULL');
    }
}
