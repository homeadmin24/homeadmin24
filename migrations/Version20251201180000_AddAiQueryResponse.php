<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add ai_query_response table for storing AI responses
 */
final class Version20251201180000_AddAiQueryResponse extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ai_query_response table for learning and comparison';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE ai_query_response (
                id INT AUTO_INCREMENT NOT NULL,
                query LONGTEXT NOT NULL,
                context JSON NOT NULL,
                provider VARCHAR(20) NOT NULL,
                response LONGTEXT NOT NULL,
                response_time DOUBLE PRECISION NOT NULL,
                cost DOUBLE PRECISION DEFAULT NULL,
                user_rating VARCHAR(10) DEFAULT NULL,
                was_used_for_training TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_provider (provider),
                INDEX idx_user_rating (user_rating),
                INDEX idx_created_at (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_query_response');
    }
}
