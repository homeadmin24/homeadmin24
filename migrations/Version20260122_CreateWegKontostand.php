<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create weg_kontostand table for tracking bank balances per WEG
 */
final class Version20260122_CreateWegKontostand extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create weg_kontostand table for WEG bank account balances (Vermögensabgrenzung)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weg_kontostand (
            id INT AUTO_INCREMENT NOT NULL,
            weg_id INT NOT NULL,
            year INT NOT NULL,
            bankkonto_typ VARCHAR(20) NOT NULL DEFAULT "hausgeld",
            stichtag_start DATE NOT NULL,
            stichtag_end DATE NOT NULL,
            saldo_start DECIMAL(10, 2) NOT NULL,
            saldo_end DECIMAL(10, 2) NOT NULL,
            bemerkung TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX IDX_WEG_KONTOSTAND_WEG (weg_id),
            UNIQUE INDEX UNIQ_WEG_KONTOSTAND_YEAR (weg_id, year, bankkonto_typ),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE weg_kontostand ADD CONSTRAINT FK_WEG_KONTOSTAND_WEG_ID
            FOREIGN KEY (weg_id) REFERENCES weg (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE weg_kontostand DROP FOREIGN KEY FK_WEG_KONTOSTAND_WEG_ID');
        $this->addSql('DROP TABLE weg_kontostand');
    }
}
