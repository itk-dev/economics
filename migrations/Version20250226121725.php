<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250226121725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE worker_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_7167E0115E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worker_group_worker (worker_group_id INT NOT NULL, worker_id INT NOT NULL, INDEX IDX_49FDB8BDF60D0B11 (worker_group_id), INDEX IDX_49FDB8BD6B20BA36 (worker_id), PRIMARY KEY(worker_group_id, worker_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE worker_group_worker ADD CONSTRAINT FK_49FDB8BDF60D0B11 FOREIGN KEY (worker_group_id) REFERENCES worker_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE worker_group_worker ADD CONSTRAINT FK_49FDB8BD6B20BA36 FOREIGN KEY (worker_id) REFERENCES worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE project ADD holiday_planning TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE worker_group_worker DROP FOREIGN KEY FK_49FDB8BDF60D0B11');
        $this->addSql('ALTER TABLE worker_group_worker DROP FOREIGN KEY FK_49FDB8BD6B20BA36');
        $this->addSql('DROP TABLE worker_group');
        $this->addSql('DROP TABLE worker_group_worker');
        $this->addSql('ALTER TABLE project DROP holiday_planning');
    }
}
