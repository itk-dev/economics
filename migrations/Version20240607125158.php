<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240607125158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue ADD hours_remaining DOUBLE PRECISION DEFAULT NULL, DROP tags');
        $this->addSql('DROP INDEX UNIQ_9FB2BF621203AA7B ON worker');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue ADD tags VARCHAR(255) DEFAULT NULL, DROP hours_remaining');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9FB2BF621203AA7B ON worker (workload)');
    }
}
