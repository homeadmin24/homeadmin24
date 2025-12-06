-- SystemConfig fixtures for Hausgeldabrechnung

-- Tax-deductible accounts
INSERT INTO system_config (config_key, config_value, category, description, is_active, created_at, updated_at) VALUES 
('hga.tax_deductible_accounts', '["040100","040101","041100","042111","045100","047000"]', 'hausgeldabrechnung', 'List of Kostenkonto numbers eligible for §35a EStG tax deduction', 1, NOW(), NOW());

-- NOTE: Balance data and Wirtschaftsplan values are not included in fixtures.
-- These should be managed via the admin interface in production environments.

-- HGA section headers
INSERT INTO system_config (config_key, config_value, category, description, is_active, created_at, updated_at) VALUES 
('hga.section_headers', '{"main_title":"HAUSGELDABRECHNUNG %s - EINZELABRECHNUNG","owner_info":"EIGENTÜMER INFORMATION:","summary":"ABRECHNUNGSÜBERSICHT:","calculation":"BERECHNUNG DES ANTEILS:","umlageschluessel":"UMLAGESCHLÜSSEL:","umlagefaehig":"1. UMLAGEFÄHIGE KOSTEN (Mieter):","nicht_umlagefaehig":"2. NICHT UMLAGEFÄHIGE KOSTEN (Mieter):","ruecklagen":"3. RÜCKLAGENZUFÜHRUNG:","tax_deductible":"STEUERBEGÜNSTIGTE LEISTUNGEN nach §35a EStG:","payment_overview":"EINZELABRECHNUNG DER ZAHLUNGEN %s:","balance_development":"KONTOSTANDSENTWICKLUNG %s:","wirtschaftsplan":"VERMÖGENSÜBERSICHT UND WIRTSCHAFTSPLAN %s","end":"ENDE DER HAUSGELDABRECHNUNG"}', 'hausgeldabrechnung', 'Standard section headers for HGA reports', 1, NOW(), NOW());

-- HGA standard texts
INSERT INTO system_config (config_key, config_value, category, description, is_active, created_at, updated_at) VALUES 
('hga.standard_texts', '{"tax_deductible_info":"✅ Ihr steuerlich absetzbarer Betrag (100%% der Arbeits-/Fahrtkosten inkl. MwSt.): %s EUR","tax_notice":["HINWEIS: Diese Beträge können Sie in Ihrer Steuererklärung als haushaltsnahe","Dienstleistungen geltend machen (20% davon, max. 1.200 EUR Steuerermäßigung pro Jahr).","","Bitte reichen Sie die detaillierte Abrechnung zusammen mit den Handwerkerrechnungen","beim Finanzamt ein."],"balance_notice":"Hinweis: Der aktuelle Kontostand deckt den Mehrbedarf vollständig, daher keine Erhöhung der Hausgeld-Vorschüsse."}', 'hausgeldabrechnung', 'Standard text templates for HGA reports', 1, NOW(), NOW());

-- Account category mappings
INSERT INTO system_config (config_key, config_value, category, description, is_active, created_at, updated_at) VALUES 
('hga.account_categories', '{"heizung_wasser":{"name":"HEIZUNG/WASSER/ABRECHNUNG","accounts":["041800"]},"versicherung":{"name":"Versicherungen","accounts":["046000","046200"]},"verwaltung":{"name":"Verwaltung","accounts":["050000","052000"]},"instandhaltung":{"name":"Instandhaltung/Reparaturen","accounts":["045100","044000"]},"sonstiges":{"name":"Sonstige","accounts":[]}}', 'hausgeldabrechnung', 'Kostenkonto category mappings for report grouping', 1, NOW(), NOW());