<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260520091548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project_codeowner (project_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_B592B971166D1F9C (project_id), INDEX IDX_B592B9716B20BA36 (worker_id), PRIMARY KEY(project_id, worker_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE project_codeowner ADD CONSTRAINT FK_B592B971166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project_codeowner ADD CONSTRAINT FK_B592B9716B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD github_repos LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_agreement DROP leantime_url, DROP git_repos');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project_codeowner DROP FOREIGN KEY FK_B592B971166D1F9C');
        $this->addSql('ALTER TABLE project_codeowner DROP FOREIGN KEY FK_B592B9716B20BA36');
        $this->addSql('DROP TABLE project_codeowner');
        $this->addSql('ALTER TABLE project DROP github_repos');
        $this->addSql('ALTER TABLE service_agreement ADD leantime_url VARCHAR(255) DEFAULT NULL, ADD git_repos LONGTEXT DEFAULT NULL');
    }
}
