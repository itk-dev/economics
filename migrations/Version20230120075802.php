<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename fields.
 */
final class Version20230120075802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice CHANGE paid_by_account legacy_paid_by_account VARCHAR(255)');
        $this->addSql('ALTER TABLE invoice CHANGE default_pay_to_account legacy_default_pay_to_account VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice CHANGE legacy_paid_by_account paid_by_account VARCHAR(255)');
        $this->addSql('ALTER TABLE invoice CHANGE legacy_default_pay_to_account default_pay_to_account VARCHAR(255)');
    }
}
