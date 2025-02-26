<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250226111820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE worker_worker_group (worker_id INT NOT NULL, worker_group_id INT NOT NULL, INDEX IDX_74AE3F1E6B20BA36 (worker_id), INDEX IDX_74AE3F1EF60D0B11 (worker_group_id), PRIMARY KEY(worker_id, worker_group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worker_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_7167E0115E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE worker_worker_group ADD CONSTRAINT FK_74AE3F1E6B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worker_worker_group ADD CONSTRAINT FK_74AE3F1EF60D0B11 FOREIGN KEY (worker_group_id) REFERENCES worker_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD holiday_planning TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worker_worker_group DROP FOREIGN KEY FK_74AE3F1E6B20BA36');
        $this->addSql('ALTER TABLE worker_worker_group DROP FOREIGN KEY FK_74AE3F1EF60D0B11');
        $this->addSql('DROP TABLE worker_worker_group');
        $this->addSql('DROP TABLE worker_group');
        $this->addSql('ALTER TABLE project DROP holiday_planning');
    }
}
