<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240108140231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE view (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view_data_provider (view_id INT NOT NULL, data_provider_id INT NOT NULL, INDEX IDX_4AE832DD31518C7 (view_id), INDEX IDX_4AE832DDF593F7E0 (data_provider_id), PRIMARY KEY(view_id, data_provider_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE view_project (view_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_6EEC51431518C7 (view_id), INDEX IDX_6EEC514166D1F9C (project_id), PRIMARY KEY(view_id, project_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE view_data_provider ADD CONSTRAINT FK_4AE832DD31518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_data_provider ADD CONSTRAINT FK_4AE832DDF593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_project ADD CONSTRAINT FK_6EEC51431518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_project ADD CONSTRAINT FK_6EEC514166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE view_data_provider DROP FOREIGN KEY FK_4AE832DD31518C7');
        $this->addSql('ALTER TABLE view_data_provider DROP FOREIGN KEY FK_4AE832DDF593F7E0');
        $this->addSql('ALTER TABLE view_project DROP FOREIGN KEY FK_6EEC51431518C7');
        $this->addSql('ALTER TABLE view_project DROP FOREIGN KEY FK_6EEC514166D1F9C');
        $this->addSql('DROP TABLE view');
        $this->addSql('DROP TABLE view_data_provider');
        $this->addSql('DROP TABLE view_project');
    }
}
