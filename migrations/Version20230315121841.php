<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230315121841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_billing (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, period_start DATETIME NOT NULL, period_end DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_7A3C40CF166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_billing ADD CONSTRAINT FK_7A3C40CF166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE invoice ADD project_billing_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174423A427B5 FOREIGN KEY (project_billing_id) REFERENCES project_billing (id)');
        $this->addSql('CREATE INDEX IDX_9065174423A427B5 ON invoice (project_billing_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174423A427B5');
        $this->addSql('ALTER TABLE project_billing DROP FOREIGN KEY FK_7A3C40CF166D1F9C');
        $this->addSql('DROP TABLE project_billing');
        $this->addSql('DROP INDEX IDX_9065174423A427B5 ON invoice');
        $this->addSql('ALTER TABLE invoice DROP project_billing_id');
    }
}
