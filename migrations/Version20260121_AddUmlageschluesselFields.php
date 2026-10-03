<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add gesamtumlage and umlage_typ fields to umlageschluessel table
 */
final class Version20260121_AddUmlageschluesselFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gesamtumlage and umlage_typ fields to umlageschluessel table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE umlageschluessel ADD gesamtumlage VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE umlageschluessel ADD umlage_typ VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE umlageschluessel DROP gesamtumlage');
        $this->addSql('ALTER TABLE umlageschluessel DROP umlage_typ');
    }
}
