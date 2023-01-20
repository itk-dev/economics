<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230120080952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice ADD default_receiver_account_id INT DEFAULT NULL, ADD payer_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517447113B22E FOREIGN KEY (default_receiver_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744C42B424A FOREIGN KEY (payer_account_id) REFERENCES account (id)');
        $this->addSql('CREATE INDEX IDX_906517447113B22E ON invoice (default_receiver_account_id)');
        $this->addSql('CREATE INDEX IDX_90651744C42B424A ON invoice (payer_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517447113B22E');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744C42B424A');
        $this->addSql('DROP INDEX IDX_906517447113B22E ON invoice');
        $this->addSql('DROP INDEX IDX_90651744C42B424A ON invoice');
        $this->addSql('ALTER TABLE invoice DROP default_receiver_account_id, DROP payer_account_id');
    }
}
