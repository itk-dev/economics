<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241121151639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE epic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE issue_epic (issue_id INT NOT NULL, epic_id INT NOT NULL, INDEX IDX_412E98BD5E7AA58C (issue_id), INDEX IDX_412E98BD6B71E00E (epic_id), PRIMARY KEY(issue_id, epic_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE issue_epic ADD CONSTRAINT FK_412E98BD5E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE issue_epic ADD CONSTRAINT FK_412E98BD6B71E00E FOREIGN KEY (epic_id) REFERENCES epic (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue_epic DROP FOREIGN KEY FK_412E98BD5E7AA58C');
        $this->addSql('ALTER TABLE issue_epic DROP FOREIGN KEY FK_412E98BD6B71E00E');
        $this->addSql('DROP TABLE epic');
        $this->addSql('DROP TABLE issue_epic');
    }
}
