<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216_DropHausgeldabrechnung extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy hausgeldabrechnung table — replaced by dokument.hga_data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hausgeldabrechnung DROP FOREIGN KEY FK_1B4789F3C9C6B77F');
        $this->addSql('DROP TABLE hausgeldabrechnung');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hausgeldabrechnung (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, jahr INT NOT NULL, pdf_pfad VARCHAR(255) DEFAULT NULL, erstellungsdatum DATE NOT NULL, gesamtkosten NUMERIC(10, 2) NOT NULL, INDEX IDX_1B4789F3C9C6B77F (weg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hausgeldabrechnung ADD CONSTRAINT FK_1B4789F3C9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
    }
}
