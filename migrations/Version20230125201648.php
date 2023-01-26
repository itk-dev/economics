<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230125201648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE version (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, name VARCHAR(255) NOT NULL, project_tracker_id VARCHAR(255) NOT NULL, INDEX IDX_BF1CD3C3166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE version_worklog (version_id INT NOT NULL, worklog_id INT NOT NULL, INDEX IDX_AE831B7A4BBC2705 (version_id), INDEX IDX_AE831B7A48A4CA35 (worklog_id), PRIMARY KEY(version_id, worklog_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C3166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A4BBC2705 FOREIGN KEY (version_id) REFERENCES version (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE version_worklog ADD CONSTRAINT FK_AE831B7A48A4CA35 FOREIGN KEY (worklog_id) REFERENCES worklog (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C3166D1F9C');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A4BBC2705');
        $this->addSql('ALTER TABLE version_worklog DROP FOREIGN KEY FK_AE831B7A48A4CA35');
        $this->addSql('DROP TABLE version');
        $this->addSql('DROP TABLE version_worklog');
    }
}
