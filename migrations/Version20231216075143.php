<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231216075143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * NOTE: The generated up and down migrations are equal.
     *
     * Running `up` will change roles, from
     *   `roles` longtext DEFAULT NULL COMMENT '(DC2Type:json)'
     * to
     *   `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '(DC2Type:json)' CHECK (json_valid(`roles`)),
     * but running `down` will not change it back.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // Empty because down will not result in a schema change.
    }
}
