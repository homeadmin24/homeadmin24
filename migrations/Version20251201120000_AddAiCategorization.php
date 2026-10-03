<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add AI categorization support tables and fields
 * - Add ai_confidence and ai_reasoning to zahlung table
 * - Create kategorisierung_correction table for tracking corrections
 * - Create kategorisierung_pattern_stats table for pattern analytics
 */
final class Version20251201120000_AddAiCategorization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI categorization support: correction tracking and pattern statistics';
    }

    public function up(Schema $schema): void
    {
        // Add AI fields to zahlung table
        $this->addSql('ALTER TABLE zahlung ADD ai_confidence DECIMAL(3, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE zahlung ADD ai_reasoning TEXT DEFAULT NULL');

        // Create kategorisierung_correction table
        $this->addSql('CREATE TABLE kategorisierung_correction (
            id INT AUTO_INCREMENT NOT NULL,
            zahlung_id INT NOT NULL,
            suggested_kostenkonto_id INT DEFAULT NULL,
            suggested_kategorie_id INT DEFAULT NULL,
            actual_kostenkonto_id INT NOT NULL,
            actual_kategorie_id INT NOT NULL,
            suggested_confidence DECIMAL(3, 2) DEFAULT NULL,
            suggested_reasoning TEXT DEFAULT NULL,
            correction_type VARCHAR(50) NOT NULL,
            correction_source VARCHAR(50) NOT NULL DEFAULT "manual_edit",
            zahlung_bezeichnung VARCHAR(255) NOT NULL,
            zahlung_partner VARCHAR(255) DEFAULT NULL,
            zahlung_betrag DECIMAL(10, 2) NOT NULL,
            zahlung_datum DATE NOT NULL,
            created_at DATETIME NOT NULL,
            created_by_id INT NOT NULL,
            INDEX IDX_KAT_CORR_ZAHLUNG (zahlung_id),
            INDEX IDX_KAT_CORR_SUGGESTED_KONTO (suggested_kostenkonto_id),
            INDEX IDX_KAT_CORR_ACTUAL_KONTO (actual_kostenkonto_id),
            INDEX IDX_KAT_CORR_CREATED_AT (created_at),
            INDEX IDX_KAT_CORR_PARTNER (zahlung_partner(100)),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_ZAHLUNG FOREIGN KEY (zahlung_id) REFERENCES zahlung (id)');
        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_SUGG_KONTO FOREIGN KEY (suggested_kostenkonto_id) REFERENCES kostenkonto (id)');
        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_ACTUAL_KONTO FOREIGN KEY (actual_kostenkonto_id) REFERENCES kostenkonto (id)');
        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_SUGG_KAT FOREIGN KEY (suggested_kategorie_id) REFERENCES zahlungskategorie (id)');
        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_ACTUAL_KAT FOREIGN KEY (actual_kategorie_id) REFERENCES zahlungskategorie (id)');
        $this->addSql('ALTER TABLE kategorisierung_correction ADD CONSTRAINT FK_KAT_CORR_USER FOREIGN KEY (created_by_id) REFERENCES user (id)');

        // Create kategorisierung_pattern_stats table
        $this->addSql('CREATE TABLE kategorisierung_pattern_stats (
            id INT AUTO_INCREMENT NOT NULL,
            pattern_type VARCHAR(50) NOT NULL,
            pattern_value VARCHAR(255) NOT NULL,
            suggested_kostenkonto_id INT DEFAULT NULL,
            success_count INT DEFAULT 0 NOT NULL,
            failure_count INT DEFAULT 0 NOT NULL,
            total_count INT DEFAULT 0 NOT NULL,
            accuracy_rate DECIMAL(5, 2) NOT NULL,
            last_success_at DATETIME DEFAULT NULL,
            last_failure_at DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_PATTERN_KONTO (suggested_kostenkonto_id),
            INDEX IDX_PATTERN_ACCURACY (accuracy_rate),
            UNIQUE INDEX UNIQ_PATTERN (pattern_type, pattern_value, suggested_kostenkonto_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE kategorisierung_pattern_stats ADD CONSTRAINT FK_PATTERN_KONTO FOREIGN KEY (suggested_kostenkonto_id) REFERENCES kostenkonto (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_ZAHLUNG');
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_SUGG_KONTO');
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_ACTUAL_KONTO');
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_SUGG_KAT');
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_ACTUAL_KAT');
        $this->addSql('ALTER TABLE kategorisierung_correction DROP FOREIGN KEY FK_KAT_CORR_USER');
        $this->addSql('DROP TABLE kategorisierung_correction');

        $this->addSql('ALTER TABLE kategorisierung_pattern_stats DROP FOREIGN KEY FK_PATTERN_KONTO');
        $this->addSql('DROP TABLE kategorisierung_pattern_stats');

        // Remove AI fields from zahlung
        $this->addSql('ALTER TABLE zahlung DROP ai_confidence');
        $this->addSql('ALTER TABLE zahlung DROP ai_reasoning');
    }
}
