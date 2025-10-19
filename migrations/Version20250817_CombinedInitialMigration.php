<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Combined migration from all individual migrations.
 * This creates the complete database schema in one operation.
 */
final class Version20250817_CombinedInitialMigration extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Combined initial migration - creates complete database schema';
    }

    public function up(Schema $schema): void
    {
        // Create base tables
        $this->addSql('CREATE TABLE dienstleister (id INT AUTO_INCREMENT NOT NULL, bezeichnung VARCHAR(255) NOT NULL, art_dienstleister VARCHAR(255) DEFAULT NULL, vertrag VARCHAR(255) DEFAULT NULL, datum_inkrafttreten DATE DEFAULT NULL, vertragsende INT DEFAULT NULL, preis_pro_jahr NUMERIC(10, 2) DEFAULT NULL, datum_unterzeichnung DATE DEFAULT NULL, kuendigungsfrist INT DEFAULT NULL, vertragsreferenz VARCHAR(255) DEFAULT NULL, parser_config JSON DEFAULT NULL, parser_class VARCHAR(255) DEFAULT NULL, ai_parsing_prompt LONGTEXT DEFAULT NULL, parser_enabled TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE weg (id INT AUTO_INCREMENT NOT NULL, bezeichnung VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE weg_einheit (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, nummer VARCHAR(255) NOT NULL, bezeichnung VARCHAR(255) NOT NULL, hauptwohneinheit TINYINT(1) NOT NULL, miteigentuemer VARCHAR(255) NOT NULL, miteigentumsanteile VARCHAR(255) NOT NULL, stimme VARCHAR(255) DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, telefon VARCHAR(255) DEFAULT NULL, hebeanlage VARCHAR(10) DEFAULT NULL COMMENT "Hebeanlage cost distribution fraction (e.g., 2/6, 1/6)", INDEX IDX_BCFA73BFC9C6B77F (weg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE umlageschluessel (id INT AUTO_INCREMENT NOT NULL, schluessel VARCHAR(10) NOT NULL, bezeichnung VARCHAR(255) NOT NULL, beschreibung LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_B1B6A157AD8C79B6 (schluessel), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE kostenkonto (id INT AUTO_INCREMENT NOT NULL, umlageschluessel_id INT DEFAULT NULL, bezeichnung VARCHAR(255) NOT NULL, nummer VARCHAR(255) DEFAULT NULL, kategorisierungs_typ VARCHAR(255) NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, tax_deductible TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_6B6A15679AAC66A5 (umlageschluessel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE rechnung (id INT AUTO_INCREMENT NOT NULL, dienstleister_id INT DEFAULT NULL, information VARCHAR(255) NOT NULL, ausstehend TINYINT(1) NOT NULL, rechnungsnummer VARCHAR(255) DEFAULT NULL, betrag_mit_steuern NUMERIC(10, 2) NOT NULL, gesamt_mw_st NUMERIC(10, 2) DEFAULT NULL, datum_leistung DATE DEFAULT NULL, faelligkeitsdatum DATE DEFAULT NULL, arbeits_fahrtkosten NUMERIC(10, 2) DEFAULT NULL, INDEX IDX_D490F3E7829F0FEA (dienstleister_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE zahlungskategorie (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, beschreibung LONGTEXT DEFAULT NULL, ist_positiver_betrag TINYINT(1) NOT NULL, field_config JSON DEFAULT NULL, validation_rules JSON DEFAULT NULL, help_text LONGTEXT DEFAULT NULL, sort_order INT DEFAULT NULL, is_active TINYINT(1) NOT NULL, allows_zero_amount TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE zahlung (id INT AUTO_INCREMENT NOT NULL, hauptkategorie_id INT DEFAULT NULL, kostenkonto_id INT DEFAULT NULL, eigentuemer_id INT DEFAULT NULL, rechnung_id INT DEFAULT NULL, dienstleister_id INT DEFAULT NULL, datum DATE NOT NULL, bezeichnung VARCHAR(255) NOT NULL, betrag NUMERIC(10, 2) NOT NULL, gesamt_mw_st NUMERIC(10, 2) DEFAULT NULL, hnd_anteil NUMERIC(10, 2) DEFAULT NULL, abrechnungsjahr_zuordnung INT DEFAULT NULL, is_simulation TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_B4FCE0BAAFE663F7 (hauptkategorie_id), INDEX IDX_B4FCE0BAB10B76AC (kostenkonto_id), INDEX IDX_B4FCE0BACF3B3DE8 (eigentuemer_id), INDEX IDX_B4FCE0BA57222FB (rechnung_id), INDEX IDX_B4FCE0BA829F0FEA (dienstleister_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE dokument (id INT AUTO_INCREMENT NOT NULL, rechnung_id INT DEFAULT NULL, dienstleister_id INT DEFAULT NULL, weg_id INT DEFAULT NULL, dateiname VARCHAR(255) NOT NULL, dateipfad VARCHAR(500) NOT NULL, dateityp VARCHAR(50) DEFAULT NULL, dategroesse INT DEFAULT NULL, upload_datum DATETIME NOT NULL, kategorie VARCHAR(100) DEFAULT NULL, beschreibung LONGTEXT DEFAULT NULL, abrechnungs_jahr INT DEFAULT NULL, einheit_nummer VARCHAR(10) DEFAULT NULL, format VARCHAR(10) DEFAULT NULL, INDEX IDX_343A081B57222FB (rechnung_id), INDEX IDX_343A081B829F0FEA (dienstleister_id), INDEX IDX_343A081BC9C6B77F (weg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE hausgeldabrechnung (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, jahr INT NOT NULL, pdf_pfad VARCHAR(255) DEFAULT NULL, erstellungsdatum DATE NOT NULL, gesamtkosten NUMERIC(10, 2) NOT NULL, INDEX IDX_1B4789F3C9C6B77F (weg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE heiz_wasserkosten (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, weg_einheit_id INT DEFAULT NULL, jahr INT NOT NULL, is_weg_gesamt TINYINT(1) NOT NULL, heizkosten NUMERIC(10, 2) NOT NULL, wasser_kosten NUMERIC(10, 2) NOT NULL, sonstige_kosten NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2A6BEC39C9C6B77F (weg_id), INDEX IDX_2A6BEC39F1394D96 (weg_einheit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE monats_saldo (id INT AUTO_INCREMENT NOT NULL, weg_id INT NOT NULL, balance_month DATE NOT NULL, opening_balance NUMERIC(10, 2) NOT NULL, closing_balance NUMERIC(10, 2) NOT NULL, transaction_sum NUMERIC(10, 2) NOT NULL, transaction_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_22C96DFAC9C6B77F (weg_id), UNIQUE INDEX unique_weg_month (weg_id, balance_month), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE unit_monthly_payment (id INT AUTO_INCREMENT NOT NULL, weg_einheit_id INT NOT NULL, year INT NOT NULL, monthly_amount NUMERIC(10, 2) NOT NULL, yearly_advance_payment NUMERIC(10, 2) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_unit_monthly_payment_weg_einheit (weg_einheit_id), UNIQUE INDEX unique_unit_year (weg_einheit_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Authentication tables
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, display_name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE user_role (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_2DE8C6A3A76ED395 (user_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY(user_id, role_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE system_config (id INT AUTO_INCREMENT NOT NULL, config_key VARCHAR(255) NOT NULL, config_value JSON DEFAULT NULL, description VARCHAR(500) DEFAULT NULL, category VARCHAR(50) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_config_key (config_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE weg_einheit ADD CONSTRAINT FK_BCFA73BFC9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
        $this->addSql('ALTER TABLE kostenkonto ADD CONSTRAINT FK_6B6A15679AAC66A5 FOREIGN KEY (umlageschluessel_id) REFERENCES umlageschluessel (id)');
        $this->addSql('ALTER TABLE rechnung ADD CONSTRAINT FK_D490F3E7829F0FEA FOREIGN KEY (dienstleister_id) REFERENCES dienstleister (id)');
        $this->addSql('ALTER TABLE zahlung ADD CONSTRAINT FK_B4FCE0BAAFE663F7 FOREIGN KEY (hauptkategorie_id) REFERENCES zahlungskategorie (id)');
        $this->addSql('ALTER TABLE zahlung ADD CONSTRAINT FK_B4FCE0BAB10B76AC FOREIGN KEY (kostenkonto_id) REFERENCES kostenkonto (id)');
        $this->addSql('ALTER TABLE zahlung ADD CONSTRAINT FK_B4FCE0BACF3B3DE8 FOREIGN KEY (eigentuemer_id) REFERENCES weg_einheit (id)');
        $this->addSql('ALTER TABLE zahlung ADD CONSTRAINT FK_B4FCE0BA57222FB FOREIGN KEY (rechnung_id) REFERENCES rechnung (id)');
        $this->addSql('ALTER TABLE zahlung ADD CONSTRAINT FK_B4FCE0BA829F0FEA FOREIGN KEY (dienstleister_id) REFERENCES dienstleister (id)');
        $this->addSql('ALTER TABLE dokument ADD CONSTRAINT FK_343A081B57222FB FOREIGN KEY (rechnung_id) REFERENCES rechnung (id)');
        $this->addSql('ALTER TABLE dokument ADD CONSTRAINT FK_343A081B829F0FEA FOREIGN KEY (dienstleister_id) REFERENCES dienstleister (id)');
        $this->addSql('ALTER TABLE dokument ADD CONSTRAINT FK_343A081BC9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
        $this->addSql('ALTER TABLE hausgeldabrechnung ADD CONSTRAINT FK_1B4789F3C9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
        $this->addSql('ALTER TABLE heiz_wasserkosten ADD CONSTRAINT FK_2A6BEC39C9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
        $this->addSql('ALTER TABLE heiz_wasserkosten ADD CONSTRAINT FK_2A6BEC39F1394D96 FOREIGN KEY (weg_einheit_id) REFERENCES weg_einheit (id)');
        $this->addSql('ALTER TABLE monats_saldo ADD CONSTRAINT FK_A0A1A455C9C6B77F FOREIGN KEY (weg_id) REFERENCES weg (id)');
        $this->addSql('ALTER TABLE unit_monthly_payment ADD CONSTRAINT FK_unit_monthly_payment_weg_einheit FOREIGN KEY (weg_einheit_id) REFERENCES weg_einheit (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');

        // Insert zahlungskategorie data
        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Rechnung von Dienstleister', 'Rechnungen von Handwerkern, Versicherungen, Versorgern etc.', 0,
         '{\"show\": [\"kostenkonto\", \"dienstleister\", \"rechnung\", \"mehrwertsteuer\"], \"required\": [\"kostenkonto\", \"dienstleister\"]}',
         '{\"betrag\": {\"max\": 0}, \"kostenkonto\": {\"not_equals\": \"099900\"}}',
         'Für alle Rechnungen von externen Dienstleistern mit Kostenkontozuordnung',
         1, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Direktbuchung Kostenkonto', 'Direkte Buchung auf ein Kostenkonto ohne Dienstleisterbezug', 0,
         '{\"show\": [\"kostenkonto\"], \"required\": [\"kostenkonto\"]}',
         '{\"betrag\": {\"max\": 0}, \"kostenkonto\": {\"not_equals\": \"099900\"}}',
         'Für interne Buchungen, Korrekturen oder Kosten ohne spezifischen Dienstleister',
         2, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Auslagenerstattung Eigentümer', 'Erstattung von Auslagen die ein Eigentümer getätigt hat', 0,
         '{\"show\": [\"eigentuemer\", \"kostenkonto\", \"dienstleister\", \"mehrwertsteuer\"], \"required\": [\"eigentuemer\", \"kostenkonto\"]}',
         '{\"betrag\": {\"max\": 0}}',
         'Wenn ein Eigentümer Kosten vorgestreckt hat und diese erstattet bekommt',
         3, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Rückzahlung an Eigentümer', 'Rückzahlung von Guthaben an einen Eigentümer', 0,
         '{\"show\": [\"eigentuemer\"], \"required\": [\"eigentuemer\"]}',
         '{\"betrag\": {\"max\": 0}}',
         'Für Rückzahlungen aus der Jahresabrechnung oder Guthaben',
         4, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Bankgebühren', 'Kontoführungsgebühren und andere Bankkosten', 0,
         '{\"show\": [], \"required\": []}',
         '{\"betrag\": {\"max\": 0}}',
         'Automatische Bankgebühren und Kontoführungskosten',
         5, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Hausgeld-Zahlung', 'Monatliche Hausgeld- oder Wohngeldzahlungen der Eigentümer', 1,
         '{\"show\": [\"eigentuemer\"], \"required\": [\"eigentuemer\"], \"auto_set\": {\"kostenkonto\": \"099900\"}}',
         '{\"betrag\": {\"min\": 0.01}}',
         'Reguläre monatliche Zahlungen der Eigentümer',
         10, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Sonderumlage', 'Sonderumlagen für größere Reparaturen oder Investitionen', 1,
         '{\"show\": [\"eigentuemer\"], \"required\": [\"eigentuemer\"]}',
         '{\"betrag\": {\"min\": 0.01}}',
         'Einmalige Sonderumlagen beschlossen in der Eigentümerversammlung',
         11, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Gutschrift Dienstleister', 'Rückerstattungen und Gutschriften von Dienstleistern', 1,
         '{\"show\": [\"kostenkonto\", \"dienstleister\"], \"required\": [\"kostenkonto\", \"dienstleister\"]}',
         '{\"betrag\": {\"min\": 0.01}}',
         'Rückerstattungen von Versorgern, Versicherungen etc.',
         12, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Zinserträge', 'Zinsen auf Giro- und Tagesgeldkonten', 1,
         '{\"show\": [], \"required\": [], \"auto_set\": {\"kostenkonto\": \"049000\"}}',
         '{\"betrag\": {\"min\": 0.01}}',
         'Automatische Zinseinträge der Bank',
         13, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Sonstige Einnahme', 'Andere Einnahmen mit Kostenkontozuordnung', 1,
         '{\"show\": [\"kostenkonto\"], \"required\": [\"kostenkonto\"]}',
         '{\"betrag\": {\"min\": 0.01}}',
         'Für alle anderen Einnahmen die einem Kostenkonto zugeordnet werden',
         14, 1, 0)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Umbuchung', 'Interne Umbuchung zwischen Kostenkonten', 0,
         '{\"show\": [\"kostenkonto\", \"kostenkonto_to\"], \"required\": [\"kostenkonto\", \"kostenkonto_to\"]}',
         '{\"kostenkonto\": {\"not_equals_field\": \"kostenkonto_to\"}}',
         'Für Umbuchungen zwischen verschiedenen Kostenkonten',
         20, 1, 1)");

        $this->addSql("INSERT INTO zahlungskategorie (name, beschreibung, ist_positiver_betrag, field_config, validation_rules, help_text, sort_order, is_active, allows_zero_amount) VALUES
        ('Korrektur', 'Korrekturbuchung für fehlerhafte Einträge', 0,
         '{\"show\": [\"kostenkonto\", \"reference_zahlung\"], \"required\": [\"kostenkonto\"]}',
         '{}',
         'Zur Korrektur von fehlerhaften Buchungen',
         21, 1, 1)");
    }

    public function down(Schema $schema): void
    {
        // Drop all foreign key constraints first
        $this->addSql('ALTER TABLE weg_einheit DROP FOREIGN KEY FK_BCFA73BFC9C6B77F');
        $this->addSql('ALTER TABLE kostenkonto DROP FOREIGN KEY FK_6B6A15679AAC66A5');
        $this->addSql('ALTER TABLE rechnung DROP FOREIGN KEY FK_D490F3E7829F0FEA');
        $this->addSql('ALTER TABLE zahlung DROP FOREIGN KEY FK_B4FCE0BAAFE663F7');
        $this->addSql('ALTER TABLE zahlung DROP FOREIGN KEY FK_B4FCE0BAB10B76AC');
        $this->addSql('ALTER TABLE zahlung DROP FOREIGN KEY FK_B4FCE0BACF3B3DE8');
        $this->addSql('ALTER TABLE zahlung DROP FOREIGN KEY FK_B4FCE0BA57222FB');
        $this->addSql('ALTER TABLE zahlung DROP FOREIGN KEY FK_B4FCE0BA829F0FEA');
        $this->addSql('ALTER TABLE dokument DROP FOREIGN KEY FK_343A081B57222FB');
        $this->addSql('ALTER TABLE dokument DROP FOREIGN KEY FK_343A081B829F0FEA');
        $this->addSql('ALTER TABLE dokument DROP FOREIGN KEY FK_343A081BC9C6B77F');
        $this->addSql('ALTER TABLE hausgeldabrechnung DROP FOREIGN KEY FK_1B4789F3C9C6B77F');
        $this->addSql('ALTER TABLE heiz_wasserkosten DROP FOREIGN KEY FK_2A6BEC39C9C6B77F');
        $this->addSql('ALTER TABLE heiz_wasserkosten DROP FOREIGN KEY FK_2A6BEC39F1394D96');
        $this->addSql('ALTER TABLE monats_saldo DROP FOREIGN KEY FK_A0A1A455C9C6B77F');
        $this->addSql('ALTER TABLE unit_monthly_payment DROP FOREIGN KEY FK_unit_monthly_payment_weg_einheit');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3A76ED395');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');

        // Drop all tables
        $this->addSql('DROP TABLE dienstleister');
        $this->addSql('DROP TABLE dokument');
        $this->addSql('DROP TABLE hausgeldabrechnung');
        $this->addSql('DROP TABLE heiz_wasserkosten');
        $this->addSql('DROP TABLE kostenkonto');
        $this->addSql('DROP TABLE monats_saldo');
        $this->addSql('DROP TABLE rechnung');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE system_config');
        $this->addSql('DROP TABLE umlageschluessel');
        $this->addSql('DROP TABLE unit_monthly_payment');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_role');
        $this->addSql('DROP TABLE weg');
        $this->addSql('DROP TABLE weg_einheit');
        $this->addSql('DROP TABLE zahlung');
        $this->addSql('DROP TABLE zahlungskategorie');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
