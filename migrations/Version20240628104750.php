<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240628104750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue ADD link_to_issue VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE project DROP is_billable');
        $this->addSql('ALTER TABLE worklog DROP kind');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worklog ADD kind VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE issue DROP link_to_issue');
        $this->addSql('ALTER TABLE project ADD is_billable TINYINT(1) DEFAULT NULL');
    }
}
