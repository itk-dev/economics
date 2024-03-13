<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240313115528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE issue_product (id INT AUTO_INCREMENT NOT NULL, issue_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_76B2414C5E7AA58C (issue_id), INDEX IDX_76B2414C4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE issue_product ADD CONSTRAINT FK_76B2414C5E7AA58C FOREIGN KEY (issue_id) REFERENCES issue (id)');
        $this->addSql('ALTER TABLE issue_product ADD CONSTRAINT FK_76B2414C4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue_product DROP FOREIGN KEY FK_76B2414C5E7AA58C');
        $this->addSql('ALTER TABLE issue_product DROP FOREIGN KEY FK_76B2414C4584665A');
        $this->addSql('DROP TABLE issue_product');
    }
}
