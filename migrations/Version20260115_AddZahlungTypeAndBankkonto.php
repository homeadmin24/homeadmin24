<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260115_AddZahlungTypeAndBankkonto extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add zahlung_typ and bankkonto_typ to zahlung';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE zahlung ADD zahlung_typ VARCHAR(50) NOT NULL DEFAULT 'sonstiges'");
        $this->addSql("ALTER TABLE zahlung ADD bankkonto_typ VARCHAR(20) NOT NULL DEFAULT 'hausgeld'");
        $this->addSql("UPDATE zahlung SET zahlung_typ = 'sonstiges' WHERE zahlung_typ IS NULL OR zahlung_typ = ''");
        $this->addSql("UPDATE zahlung SET bankkonto_typ = 'hausgeld' WHERE bankkonto_typ IS NULL OR bankkonto_typ = ''");
        $this->addSql('CREATE INDEX IDX_ZAHLUNG_BANKKONTO_TYP ON zahlung (bankkonto_typ)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_ZAHLUNG_BANKKONTO_TYP ON zahlung');
        $this->addSql('ALTER TABLE zahlung DROP bankkonto_typ');
        $this->addSql('ALTER TABLE zahlung DROP zahlung_typ');
    }
}
