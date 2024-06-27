<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240626115710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_view DROP FOREIGN KEY FK_847CE747A76ED395');
        $this->addSql('ALTER TABLE user_view DROP FOREIGN KEY FK_847CE74731518C7');
        $this->addSql('ALTER TABLE view_project DROP FOREIGN KEY FK_6EEC514166D1F9C');
        $this->addSql('ALTER TABLE view_project DROP FOREIGN KEY FK_6EEC51431518C7');
        $this->addSql('ALTER TABLE view_data_provider DROP FOREIGN KEY FK_4AE832DDF593F7E0');
        $this->addSql('ALTER TABLE view_data_provider DROP FOREIGN KEY FK_4AE832DD31518C7');
        $this->addSql('DROP TABLE user_view');
        $this->addSql('DROP TABLE view_project');
        $this->addSql('DROP TABLE view');
        $this->addSql('DROP TABLE view_data_provider');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_view (user_id INT NOT NULL, view_id INT NOT NULL, INDEX IDX_847CE747A76ED395 (user_id), INDEX IDX_847CE74731518C7 (view_id), PRIMARY KEY(user_id, view_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE view_project (view_id INT NOT NULL, project_id INT NOT NULL, INDEX IDX_6EEC51431518C7 (view_id), INDEX IDX_6EEC514166D1F9C (project_id), PRIMARY KEY(view_id, project_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE view (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, updated_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, protected TINYINT(1) DEFAULT NULL, workers LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:simple_array)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE view_data_provider (view_id INT NOT NULL, data_provider_id INT NOT NULL, INDEX IDX_4AE832DD31518C7 (view_id), INDEX IDX_4AE832DDF593F7E0 (data_provider_id), PRIMARY KEY(view_id, data_provider_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_view ADD CONSTRAINT FK_847CE747A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_view ADD CONSTRAINT FK_847CE74731518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_project ADD CONSTRAINT FK_6EEC514166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_project ADD CONSTRAINT FK_6EEC51431518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_data_provider ADD CONSTRAINT FK_4AE832DDF593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE view_data_provider ADD CONSTRAINT FK_4AE832DD31518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
    }
}
