<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240104092346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP source');
        $this->addSql('ALTER TABLE issue DROP source');
        $this->addSql('ALTER TABLE worklog DROP source');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog ADD source VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE account ADD source VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE issue ADD source VARCHAR(255) NOT NULL');
    }
}
