<?php

/**
 * HGA Configuration File.
 *
 * Static configuration for Hausgeldabrechnung reports.
 * This replaces the system_config table for simple maintenance.
 */

return [
    /*
     * Bank balances and assets (Vermögensstand)
     */
    'bank_balances' => [
        'hausgeld_konto' => 6827.13,
        'ruecklagen_konto' => 0.00,
        'balance_date' => '16.07.2025', // Display date for balances
    ],

    /*
     * Wirtschaftsplan 2025 - Planned expenses
     */
    'planned_expenses' => [
        'umlagefaehig' => [
            'Heizung und Warmwasser (ext. Abrechnung)' => 7200.00,
            'Versicherungen' => 1300.00,
            'Müllentsorgung (AWM)' => 700.00,
            'Allgemeinstrom' => 600.00,
            'Hebeanlage - Wartung' => 230.00,
            'Hausmeisterkosten' => 3900.00,
        ],
        'nicht_umlagefaehig' => [
            'Heizungsreparatur und -wartung' => 850.00,
            'Reparatur Sonnenschutz' => 900.00,
            'Verwaltervergütung (Jan-Apr: 345€/Monat)' => 1380.00,
            'Verwaltervergütung (Mai-Dez: 180€/Monat)' => 1440.00,
            'Bankgebühren' => 100.00,
        ],
    ],

    /*
     * Wirtschaftsplan 2025 - Planned income
     */
    'planned_income' => [
        'monthly_total' => 1500.00,
        'annual_total' => 18000.00,
        'nachzahlungen_2024' => 1866.96,
    ],

    /*
     * Section headers for reports
     */
    'section_headers' => [
        'main_title' => 'HAUSGELDABRECHNUNG %s - EINZELABRECHNUNG',
        'owner_info' => 'EIGENTÜMER INFORMATION:',
        'summary' => 'ABRECHNUNGSÜBERSICHT:',
        'calculation' => 'BERECHNUNG DES ANTEILS:',
        'umlageschluessel' => 'UMLAGESCHLÜSSEL:',
        'umlagefaehig' => '1. UMLAGEFÄHIGE KOSTEN (Mieter):',
        'nicht_umlagefaehig' => '2. NICHT UMLAGEFÄHIGE KOSTEN (Mieter):',
        'ruecklagen' => '3. RÜCKLAGENZUFÜHRUNG:',
        'tax_deductible' => 'STEUERBEGÜNSTIGTE LEISTUNGEN nach §35a EStG:',
        'payment_overview' => 'ZAHLUNGSÜBERSICHT %s:',
        'balance_development' => 'KONTOSTANDSENTWICKLUNG %s:',
        'wirtschaftsplan' => 'VERMÖGENSÜBERSICHT UND WIRTSCHAFTSPLAN %s',
        'end' => 'ENDE DER HAUSGELDABRECHNUNG',
    ],

    /*
     * Standard text templates
     */
    'standard_texts' => [
        'tax_deductible_info' => 'Ihr steuerlich absetzbarer Betrag (100%% der Arbeits-/Fahrtkosten inkl. MwSt.): %.2f EUR',
        'tax_notice' => [
            'HINWEIS: Diese Beträge können Sie in Ihrer Steuererklärung als haushaltsnahe',
            'Dienstleistungen geltend machen (20% davon, max. 1.200 EUR Steuerermäßigung pro Jahr).',
            '',
            'Bitte reichen Sie die detaillierte Abrechnung zusammen mit den Handwerkerrechnungen',
            'beim Finanzamt ein.',
        ],
        'balance_notice' => 'Hinweis: Der aktuelle Kontostand deckt den Mehrbedarf vollständig,',
        'balance_notice_2' => 'daher keine Erhöhung der Hausgeld-Vorschüsse.',
        'result_nachzahlung' => 'Ergebnis: Nachzahlung in Höhe von %.2f €',
        'result_guthaben' => 'Ergebnis: Guthaben in Höhe von %.2f €',
    ],

    /*
     * Account category mappings for report grouping
     */
    'account_categories' => [
        'heizung_wasser' => [
            'name' => 'HEIZUNG/WASSER/ABRECHNUNG',
            'accounts' => ['041800'],
        ],
        'versicherung' => [
            'name' => 'Versicherungen',
            'accounts' => ['046000', '046200'],
        ],
        'verwaltung' => [
            'name' => 'Verwaltung',
            'accounts' => ['050000', '052000'],
        ],
        'instandhaltung' => [
            'name' => 'Instandhaltung/Reparaturen',
            'accounts' => ['045100', '044000'],
        ],
        'sonstiges' => [
            'name' => 'Sonstige',
            'accounts' => [],
        ],
    ],
];
