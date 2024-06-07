<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240606085631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE worker (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, workload DOUBLE PRECISION NOT NULL, UNIQUE INDEX UNIQ_9FB2BF62E7927C74 (email), UNIQUE INDEX UNIQ_9FB2BF621203AA7B (workload), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE issue ADD tags VARCHAR(255) DEFAULT NULL, DROP hours_remaining');
        $this->addSql('ALTER TABLE issue ADD tags VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE worker');
        $this->addSql('ALTER TABLE issue ADD hours_remaining DOUBLE PRECISION DEFAULT NULL, DROP tags');
        $this->addSql('ALTER TABLE issue DROP tags');
    }
}
