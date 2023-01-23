<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230123075506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE contact contact VARCHAR(255) DEFAULT NULL, CHANGE standard_price standard_price DOUBLE PRECISION DEFAULT NULL, CHANGE type type VARCHAR(255) DEFAULT NULL, CHANGE account account VARCHAR(255) DEFAULT NULL, CHANGE psp psp VARCHAR(255) DEFAULT NULL, CHANGE ean ean VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE contact contact VARCHAR(255) NOT NULL, CHANGE standard_price standard_price DOUBLE PRECISION NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE account account VARCHAR(255) NOT NULL, CHANGE psp psp VARCHAR(255) NOT NULL, CHANGE ean ean VARCHAR(255) NOT NULL');
    }
}
