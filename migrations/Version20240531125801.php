<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240531125801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add product from preceding worklog invoice entry as prefix on product entries.
        $this->addSql(<<<'SQL'
UPDATE
    invoice_entry
SET
    product = CONCAT(
      IF(
        ISNULL(
          -- Get "product" from preceding worklog entry (this expression is repeated below).
          (SELECT product FROM invoice_entry AS ie WHERE ie.invoice_id = invoice_entry.invoice_id AND ie.entry_type = 'worklog' AND ie.entry_index < invoice_entry.entry_index ORDER BY ie.entry_index DESC LIMIT 1)
        ),
        '',
        CONCAT(
          (SELECT product FROM invoice_entry AS ie WHERE ie.invoice_id = invoice_entry.invoice_id AND ie.entry_type = 'worklog' AND ie.entry_index < invoice_entry.entry_index ORDER BY ie.entry_index DESC LIMIT 1),
          ': '
        )
      ),
      product
    )
WHERE entry_type = 'product'
SQL);
    }

    public function down(Schema $schema): void
    {
        // There is not going back (or down)!
    }
}
