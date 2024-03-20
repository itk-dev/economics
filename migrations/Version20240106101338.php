<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240106101338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE data_provider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) DEFAULT NULL, secret VARCHAR(255) DEFAULT NULL, class VARCHAR(255) NOT NULL, enable_client_sync TINYINT(1) DEFAULT NULL, enable_account_sync TINYINT(1) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_581ABA405E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE account ADD data_provider_id INT DEFAULT NULL, DROP source');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A4F593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_7D3656A4F593F7E0 ON account (data_provider_id)');
        $this->addSql('ALTER TABLE client ADD data_provider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_C7440455F593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_C7440455F593F7E0 ON client (data_provider_id)');
        $this->addSql('ALTER TABLE issue ADD data_provider_id INT DEFAULT NULL, ADD created_by VARCHAR(255) DEFAULT NULL, ADD updated_by VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, DROP source');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233EF593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_12AD233EF593F7E0 ON issue (data_provider_id)');
        $this->addSql('ALTER TABLE project ADD data_provider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEF593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEF593F7E0 ON project (data_provider_id)');
        $this->addSql('ALTER TABLE version ADD data_provider_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE version ADD CONSTRAINT FK_BF1CD3C3F593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_BF1CD3C3F593F7E0 ON version (data_provider_id)');
        $this->addSql('ALTER TABLE worklog ADD data_provider_id INT DEFAULT NULL, DROP source');
        $this->addSql('ALTER TABLE worklog ADD CONSTRAINT FK_524AFE2EF593F7E0 FOREIGN KEY (data_provider_id) REFERENCES data_provider (id)');
        $this->addSql('CREATE INDEX IDX_524AFE2EF593F7E0 ON worklog (data_provider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_7D3656A4F593F7E0');
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_C7440455F593F7E0');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233EF593F7E0');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EEF593F7E0');
        $this->addSql('ALTER TABLE version DROP FOREIGN KEY FK_BF1CD3C3F593F7E0');
        $this->addSql('ALTER TABLE worklog DROP FOREIGN KEY FK_524AFE2EF593F7E0');
        $this->addSql('DROP TABLE data_provider');
        $this->addSql('DROP INDEX IDX_C7440455F593F7E0 ON client');
        $this->addSql('ALTER TABLE client DROP data_provider_id');
        $this->addSql('DROP INDEX IDX_524AFE2EF593F7E0 ON worklog');
        $this->addSql('ALTER TABLE worklog ADD source VARCHAR(255) NOT NULL, DROP data_provider_id');
        $this->addSql('DROP INDEX IDX_7D3656A4F593F7E0 ON account');
        $this->addSql('ALTER TABLE account ADD source VARCHAR(255) NOT NULL, DROP data_provider_id');
        $this->addSql('DROP INDEX IDX_12AD233EF593F7E0 ON issue');
        $this->addSql('ALTER TABLE issue ADD source VARCHAR(255) NOT NULL, DROP data_provider_id, DROP created_by, DROP updated_by, DROP created_at, DROP updated_at');
        $this->addSql('DROP INDEX IDX_2FB3D0EEF593F7E0 ON project');
        $this->addSql('ALTER TABLE project DROP data_provider_id');
        $this->addSql('DROP INDEX IDX_BF1CD3C3F593F7E0 ON version');
        $this->addSql('ALTER TABLE version DROP data_provider_id');
    }
}
