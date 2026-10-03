<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216_SimplifyRolesAndUserMgmt extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simplify roles: migrate role data from user_role/role tables into user.roles JSON, drop role tables, drop last_login column';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Migrate roles from user_role JOIN role into user.roles JSON array
        $this->addSql("
            UPDATE user u
            SET u.roles = (
                SELECT CONCAT('[', GROUP_CONCAT(CONCAT('\"', r.name, '\"') SEPARATOR ','), ']')
                FROM user_role ur
                JOIN role r ON ur.role_id = r.id
                WHERE ur.user_id = u.id
            )
            WHERE EXISTS (
                SELECT 1 FROM user_role ur WHERE ur.user_id = u.id
            )
        ");

        // Step 2: Ensure users without roles have empty array
        $this->addSql("UPDATE user SET roles = '[]' WHERE roles IS NULL OR roles = ''");

        // Step 3: Drop junction table and role table
        $this->addSql('DROP TABLE IF EXISTS user_role');
        $this->addSql('DROP TABLE IF EXISTS role');

        // Step 4: Drop last_login column
        $this->addSql('ALTER TABLE user DROP COLUMN last_login');
    }

    public function down(Schema $schema): void
    {
        // Recreate role table
        $this->addSql("
            CREATE TABLE role (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(50) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_57698A6A5E237E06 (name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        // Recreate user_role junction table
        $this->addSql("
            CREATE TABLE user_role (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                INDEX IDX_2DE8C6A3A76ED395 (user_id),
                INDEX IDX_2DE8C6A3D60322AC (role_id),
                PRIMARY KEY(user_id, role_id),
                CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
                CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        // Re-add last_login column
        $this->addSql('ALTER TABLE user ADD last_login DATETIME DEFAULT NULL');
    }
}
