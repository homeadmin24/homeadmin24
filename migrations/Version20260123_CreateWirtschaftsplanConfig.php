<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create wirtschaftsplan_config table for yearly JSON configs.
 */
final class Version20260123_CreateWirtschaftsplanConfig extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wirtschaftsplan_config table for per-year Wirtschaftsplan JSON data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS wirtschaftsplan_config (
            id INT AUTO_INCREMENT NOT NULL,
            year INT NOT NULL,
            data JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_WIRTSCHAFTSPLAN_YEAR (year),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wirtschaftsplan_config');
    }
}
