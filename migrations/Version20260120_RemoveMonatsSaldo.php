<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120_RemoveMonatsSaldo extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove monats_saldo table (deprecated manual balance source)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS monats_saldo');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE monats_saldo (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, balance_month DATE NOT NULL, opening_balance NUMERIC(10, 2) NOT NULL, closing_balance NUMERIC(10, 2) NOT NULL, transaction_sum NUMERIC(10, 2) NOT NULL, transaction_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_A0A1A455C9C6B77F (weg_id), UNIQUE INDEX unique_weg_month (weg_id, balance_month), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE monats_saldo ADD CONSTRAINT FK_A0A1A455C9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
    }
}
