<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240205130507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_view (user_id INT NOT NULL, view_id INT NOT NULL, INDEX IDX_847CE747A76ED395 (user_id), INDEX IDX_847CE74731518C7 (view_id), PRIMARY KEY(user_id, view_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_view ADD CONSTRAINT FK_847CE747A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_view ADD CONSTRAINT FK_847CE74731518C7 FOREIGN KEY (view_id) REFERENCES view (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_view DROP FOREIGN KEY FK_847CE747A76ED395');
        $this->addSql('ALTER TABLE user_view DROP FOREIGN KEY FK_847CE74731518C7');
        $this->addSql('DROP TABLE user_view');
    }
}
