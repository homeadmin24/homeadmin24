<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add HGA quality check support
 * - Add hga_data JSON column to dokument table
 * - Create hga_quality_feedback table for user feedback and learning
 */
final class Version20251229_AddHgaQualityChecks extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add HGA quality check support with feedback learning system';
    }

    public function up(Schema $schema): void
    {
        // Add hga_data JSON column to dokument table
        $this->addSql('ALTER TABLE dokument ADD COLUMN hga_data JSON DEFAULT NULL');

        // Create hga_quality_feedback table
        $this->addSql('CREATE TABLE hga_quality_feedback (
            id INT AUTO_INCREMENT NOT NULL,
            dokument_id INT NOT NULL,
            einheit_id INT NOT NULL,
            year INT NOT NULL,
            ai_provider VARCHAR(20) NOT NULL,
            ai_result JSON DEFAULT NULL,
            user_feedback_type VARCHAR(50) NOT NULL,
            user_description LONGTEXT DEFAULT NULL,
            helpful_rating TINYINT(1) DEFAULT NULL,
            implemented TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_HGA_FEEDBACK_DOKUMENT (dokument_id),
            INDEX IDX_HGA_FEEDBACK_EINHEIT (einheit_id),
            INDEX IDX_HGA_FEEDBACK_PROVIDER (ai_provider),
            INDEX IDX_HGA_FEEDBACK_TYPE (user_feedback_type),
            INDEX IDX_HGA_FEEDBACK_IMPLEMENTED (implemented),
            INDEX IDX_HGA_FEEDBACK_CREATED (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE hga_quality_feedback
            ADD CONSTRAINT FK_HGA_FEEDBACK_DOKUMENT_ID
            FOREIGN KEY (dokument_id) REFERENCES dokument (id)
            ON DELETE CASCADE');

        $this->addSql('ALTER TABLE hga_quality_feedback
            ADD CONSTRAINT FK_HGA_FEEDBACK_EINHEIT_ID
            FOREIGN KEY (einheit_id) REFERENCES weg_einheit (id)
            ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE hga_quality_feedback DROP FOREIGN KEY FK_HGA_FEEDBACK_DOKUMENT_ID');
        $this->addSql('ALTER TABLE hga_quality_feedback DROP FOREIGN KEY FK_HGA_FEEDBACK_EINHEIT_ID');

        // Drop tables and columns
        $this->addSql('DROP TABLE hga_quality_feedback');
        $this->addSql('ALTER TABLE dokument DROP COLUMN hga_data');
    }
}
