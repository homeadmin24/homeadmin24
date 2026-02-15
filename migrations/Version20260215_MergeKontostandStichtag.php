<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Merge _stichtag rows into base rows (4 -> 2 entries per WEG/year).
 *
 * Adds stichtag_end_periode and saldo_end_periode columns, migrates data
 * from _stichtag variants into the base row, then deletes _stichtag rows.
 */
final class Version20260215_MergeKontostandStichtag extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge weg_kontostand _stichtag rows into base rows (4->2 per year)';
    }

    public function up(Schema $schema): void
    {
        // 1. Add new columns
        $this->addSql('ALTER TABLE weg_kontostand ADD stichtag_end_periode DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE weg_kontostand ADD saldo_end_periode DECIMAL(10, 2) DEFAULT NULL');

        // 2. Migrate data: copy base row's current stichtag_end/saldo_end into new periode fields,
        //    then overwrite base row's stichtag_end/saldo_end with _stichtag values
        $this->addSql('
            UPDATE weg_kontostand base
            JOIN weg_kontostand stichtag
              ON base.weg_id = stichtag.weg_id
              AND base.year = stichtag.year
              AND CONCAT(base.bankkonto_typ, \'_stichtag\') = stichtag.bankkonto_typ
            SET
              base.stichtag_end_periode = base.stichtag_end,
              base.saldo_end_periode = base.saldo_end,
              base.stichtag_end = stichtag.stichtag_end,
              base.saldo_end = stichtag.saldo_end
        ');

        // 3. Delete _stichtag rows
        $this->addSql('DELETE FROM weg_kontostand WHERE bankkonto_typ LIKE \'%_stichtag\'');
    }

    public function down(Schema $schema): void
    {
        // Re-create _stichtag rows from merged data
        $this->addSql('
            INSERT INTO weg_kontostand (weg_id, year, bankkonto_typ, stichtag_start, stichtag_end, saldo_start, saldo_end, bemerkung, created_at, updated_at)
            SELECT
              weg_id,
              year,
              CONCAT(bankkonto_typ, \'_stichtag\'),
              stichtag_start,
              stichtag_end,
              saldo_start,
              saldo_end,
              bemerkung,
              created_at,
              NOW()
            FROM weg_kontostand
            WHERE stichtag_end_periode IS NOT NULL
        ');

        // Restore base rows to their original period-end values
        $this->addSql('
            UPDATE weg_kontostand
            SET stichtag_end = stichtag_end_periode,
                saldo_end = saldo_end_periode
            WHERE stichtag_end_periode IS NOT NULL
        ');

        // Drop new columns
        $this->addSql('ALTER TABLE weg_kontostand DROP COLUMN stichtag_end_periode');
        $this->addSql('ALTER TABLE weg_kontostand DROP COLUMN saldo_end_periode');
    }
}
