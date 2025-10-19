<?php

declare(strict_types=1);

namespace App\Service\Hga\Generator;

/**
 * TXT format report generator for HGA.
 *
 * Generates plain text reports with proper formatting and alignment.
 */
class TxtReportGenerator extends AbstractReportGenerator
{
    private const LINE_WIDTH = 120;
    private const SEPARATOR_LINE = '================================================================================';
    private const SUB_SEPARATOR = '----------------------------------------------------------------------';

    /**
     * {@inheritdoc}
     */
    public function getMimeType(): string
    {
        return 'text/plain';
    }

    /**
     * {@inheritdoc}
     */
    public function getFileExtension(): string
    {
        return 'txt';
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $reportData
     */
    protected function formatReport(array $reportData): string
    {
        $output = [];

        // Add sections
        $output[] = $this->formatHeader($reportData);
        $output[] = $this->formatOwnerInfo($reportData);
        $output[] = $this->formatSummary($reportData);
        $output[] = $this->formatCalculation($reportData);
        $output[] = $this->formatUmlageschluessel($reportData);
        $output[] = $this->formatCostSections($reportData);
        $output[] = $this->formatPaymentOverview($reportData);
        $output[] = $this->formatAccountDevelopment($reportData);
        $output[] = $this->formatWirtschaftsplan($reportData);
        $output[] = $this->formatTaxDeductible($reportData);
        $output[] = $this->formatFooter($reportData);

        return implode("\n", array_filter($output));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatHeader(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $title = \sprintf($headers['main_title'], $data['year']);

        return implode("\n", [
            self::SEPARATOR_LINE,
            $title,
            self::SEPARATOR_LINE,
            '',
            'Erstellung: ' . $this->formatDate($data['calculation_date']),
            'Objekt: ' . $data['weg']['name'] . ', ' . $data['weg']['adresse'],
            'Abrechnungszeitraum: 01.01.' . $data['year'] . ' - 31.12.' . $data['year'],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatOwnerInfo(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $einheit = $data['einheit'];

        return implode("\n", [
            '',
            $headers['owner_info'],
            str_repeat('-', 40),
            'Eigentümer: ' . $einheit['nummer'] . ' ' . $einheit['beschreibung'] . ' ' . $einheit['eigentuemer'],
            'Empfänger-Adresse: ' . $einheit['eigentuemer'] . ', ' . ($einheit['address'] ?? 'Keine Adresse hinterlegt'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatSummary(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $texts = $data['configuration']['standard_texts'];

        // Calculate final balance
        $costs = $data['costs'];
        $payments = $data['payments'];
        $gesamtkosten = $costs['gesamtkosten'];
        $zahlungsDifferenz = $payments['differenz'];

        $abrechnungsSpitze = $gesamtkosten - $payments['soll'];
        $saldo = $abrechnungsSpitze + $zahlungsDifferenz;

        return implode("\n", [
            '',
            $headers['summary'],
            str_repeat('-', 40),
            $this->getResultText($saldo, $texts),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatCalculation(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $costs = $data['costs'];
        $payments = $data['payments'];
        $wegTotals = $data['weg_totals'];

        $lines = [
            '',
            $headers['calculation'],
            str_repeat('-', 50),
            \sprintf('%-30s %15s %15s', 'Position', 'Objekt gesamt', 'Ihr Anteil'),
            str_repeat('-', 50),
        ];

        // Cost calculation
        $lines[] = \sprintf('%-30s %15s %15s',
            'Gesamtkosten',
            $this->formatCurrency($wegTotals['gesamtkosten']),
            $this->formatCurrency($costs['gesamtkosten'])
        );

        $lines[] = \sprintf('%-30s %15s %15s',
            '- Hausgeld-Vorschuss Soll',
            $this->formatCurrency($payments['weg_totals']['soll']),
            $this->formatCurrency($payments['soll'])
        );

        $lines[] = str_repeat('-', 50);

        $abrechnungsSpitze = $costs['gesamtkosten'] - $payments['soll'];
        $wegAbrechnungsSpitze = $wegTotals['gesamtkosten'] - $payments['weg_totals']['soll'];

        $lines[] = \sprintf('%-30s %15s %15s',
            '= Abrechnungsspitze',
            $this->formatCurrency($wegAbrechnungsSpitze),
            $this->formatCurrency($abrechnungsSpitze) . ' (' . ($abrechnungsSpitze >= 0 ? 'Unterdeckung' : 'Überdeckung') . ')'
        );

        // Payment calculation
        $lines[] = \sprintf('%-30s %15s %15s',
            'Hausgeld-Vorschuss Soll',
            $this->formatCurrency($payments['weg_totals']['soll']),
            $this->formatCurrency($payments['soll'])
        );

        $lines[] = \sprintf('%-30s %15s %15s',
            '- Hausgeld-Vorschuss Ist',
            $this->formatCurrency($payments['weg_totals']['ist']),
            $this->formatCurrency($payments['ist'])
        );

        $lines[] = str_repeat('-', 50);

        $lines[] = \sprintf('%-30s %15s %15s',
            '= Zahlungsdifferenz',
            $this->formatCurrency($payments['weg_totals']['differenz']),
            $this->formatCurrency($payments['differenz']) . ' (' . $payments['status'] . ')'
        );

        $saldo = $abrechnungsSpitze + $payments['differenz'];
        $lines[] = \sprintf('%-30s %30s',
            '= ABRECHNUNGS-SALDO',
            $this->formatCurrency($saldo) . ' (' . ($saldo > 0 ? 'Nachzahlung' : 'Guthaben') . ')'
        );

        $lines[] = str_repeat('=', 50);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatUmlageschluessel(array $data): string
    {
        $headers = $data['configuration']['section_headers'];

        $lines = [
            '',
            $headers['umlageschluessel'],
            str_repeat('-', self::LINE_WIDTH),
            \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s %-15s',
                'Nr.', 'Umlageschlüssel', 'Umlage', 'Zeitraum', 'Tage', 'Gesamtumlage', 'Ihr Anteil'
            ),
            str_repeat('-', self::LINE_WIDTH),
        ];

        // Add distribution keys
        $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s',
            '01*', 'ext. berechn. Heizkosten', '€ Festbetrag', $data['year'], '365',
            'Beträge siehe Ergebnisliste'
        );

        $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s',
            '02*', 'ext. berechn. Wasser-/sonst. Kosten', '€ Festbetrag', $data['year'], '365',
            'Beträge siehe Ergebnisliste'
        );

        $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s %-15s',
            '03*', 'Anzahl Einheit', 'Einheiten-anteilig', $data['year'], '365',
            '4,00', '1,00'
        );

        $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s',
            '04*', 'Festumlage', '€ Festbetrag', $data['year'], '365',
            'Beträge siehe Ergebnisliste'
        );

        $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s %-15s',
            '05*', 'Miteigentumsanteil', 'Anzahl anteilig', $data['year'], '365',
            '1.000,000', str_replace('/', ',', $data['einheit']['mea'] ?? '0')
        );

        if ($data['einheit']['hebeanlage'] ?? null) {
            $lines[] = \sprintf('%-4s %-30s %-20s %-12s %-4s %-15s %-15s',
                '06*', 'Hebeanlage', 'Spezial', $data['year'], '365',
                '6,00', str_replace('/', ',', $data['einheit']['hebeanlage'])
            );
        }

        $lines[] = str_repeat('=', self::LINE_WIDTH);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatCostSections(array $data): string
    {
        $sections = [];

        // Umlagefähige Kosten
        $sections[] = $this->formatCostSection(
            $data['configuration']['section_headers']['umlagefaehig'],
            $data['costs']['umlagefaehig']['items'],
            $data['costs']['umlagefaehig']['total'],
            'umlagefähig (Mieter)',
            $data
        );

        // Nicht umlagefähige Kosten
        $sections[] = $this->formatCostSection(
            $data['configuration']['section_headers']['nicht_umlagefaehig'],
            $data['costs']['nicht_umlagefaehig']['items'],
            $data['costs']['nicht_umlagefaehig']['total'],
            'nicht umlagefähig (Mieter)',
            $data
        );

        // Rücklagenzuführung
        $sections[] = $this->formatRuecklagenSection(
            $data['configuration']['section_headers']['ruecklagen'],
            $data['costs']['ruecklagen']['items'],
            $data['costs']['ruecklagen']['total']
        );

        // Total summary
        $sections[] = implode("\n", [
            '',
            '',
            \sprintf('%-30s %15s %15s',
                '∑ GESAMTSUMME ALLER KOSTEN',
                $this->formatCurrency($data['weg_totals']['gesamtkosten']),
                $this->formatCurrency($data['costs']['gesamtkosten'])
            ),
            self::SEPARATOR_LINE,
        ]);

        return implode("\n", $sections);
    }

    /**
     * @param array<mixed>         $items
     * @param array<string, mixed> $data
     */
    private function formatCostSection(string $title, array $items, float $total, string $summaryLabel, array $data = []): string
    {
        $lines = [
            '',
            $title,
            str_repeat('-', 50),
            \sprintf('%-35s %15s %15s', 'Kostenart', 'Gesamtkosten', 'Ihr Anteil'),
            str_repeat('-', 50),
        ];

        // Add external costs if this is umlagefähig section
        if (str_contains($title, 'UMLAGEFÄHIGE')) {
            $lines[] = 'HEIZUNG/WASSER/ABRECHNUNG:';

            if (isset($data['external_costs'])) {
                $lines[] = \sprintf('%-35s %15s %15s',
                    'ext. berechn. Heizkosten 01*',
                    $this->formatCurrency($data['external_costs']['heating']['total']),
                    $this->formatCurrency($data['external_costs']['heating']['unit_share'])
                );

                $lines[] = \sprintf('%-35s %15s %15s',
                    'ext. berechn. Wasser-/sonst. Kosten 02*',
                    $this->formatCurrency($data['external_costs']['water']['total']),
                    $this->formatCurrency($data['external_costs']['water']['unit_share'])
                );

                $lines[] = \sprintf('%-35s %15s %15s',
                    '∑ Zwischensumme: Heizung/Wasser/Abrechnung',
                    $this->formatCurrency($data['external_costs']['total']),
                    $this->formatCurrency($data['external_costs']['unit_total'])
                );

                $lines[] = '';
            }

            $lines[] = 'SONSTIGE UMLAGEFÄHIGE KOSTEN:';
        }

        // Add cost items
        foreach ($items as $item) {
            $lines[] = \sprintf('%-35s %15s %15s',
                $item['kostenkonto'] . ' - ' . $item['beschreibung'] . ' ' . $item['verteilungsschluessel'],
                $this->formatCurrency($item['total']),
                $this->formatCurrency($item['anteil'])
            );
        }

        if (!str_contains($title, 'UMLAGEFÄHIGE')) {
            $lines[] = str_repeat('-', 50);
            $lines[] = \sprintf('%-35s %15s %15s',
                '∑ Zwischensumme: Sonstige',
                $this->formatCurrency(array_sum(array_column($items, 'total'))),
                $this->formatCurrency($total)
            );
        }

        $lines[] = '';
        $lines[] = \sprintf('%-35s %15s %15s',
            '∑ SUMME: ' . $summaryLabel,
            $this->formatCurrency(array_sum(array_column($items, 'total'))),
            $this->formatCurrency($total)
        );
        $lines[] = str_repeat('=', 50);

        return implode("\n", $lines);
    }

    /**
     * @param array<mixed> $items
     */
    private function formatRuecklagenSection(string $title, array $items, float $total): string
    {
        $lines = [
            '',
            $title,
            str_repeat('-', 50),
            \sprintf('%-35s %15s %15s', 'Art der Rücklagenzuführung', 'Gesamtbetrag', 'Ihr Anteil'),
            str_repeat('-', 50),
        ];

        foreach ($items as $item) {
            $lines[] = \sprintf('%-35s %15s %15s',
                $item['kostenkonto'] . ' - ' . $item['beschreibung'] . ' ' . $item['verteilungsschluessel'],
                $this->formatCurrency($item['total']),
                $this->formatCurrency($item['anteil'])
            );
        }

        $lines[] = str_repeat('-', 50);
        $lines[] = \sprintf('%-35s %15s %15s',
            '∑ SUMME: Rücklagenzuführung',
            $this->formatCurrency(array_sum(array_column($items, 'total'))),
            $this->formatCurrency($total)
        );
        $lines[] = str_repeat('=', 50);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatPaymentOverview(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $title = \sprintf($headers['payment_overview'], $data['year']);

        $lines = [
            '',
            $title,
            self::SUB_SEPARATOR,
            'Debitor: ' . $data['einheit']['nummer'] . ' ' . $data['einheit']['beschreibung'] . ' ' . $data['einheit']['eigentuemer'],
            'Zeitraum: 01.01.' . $data['year'] . ' - 31.12.' . $data['year'],
            self::SUB_SEPARATOR,
            \sprintf('%-12s %-45s %15s', 'Datum', 'Beschreibung', 'Betrag'),
            self::SUB_SEPARATOR,
        ];

        foreach ($data['payments']['payment_details'] as $payment) {
            $lines[] = \sprintf('%-12s %-45s %15s',
                $this->formatDate($payment['datum']),
                $payment['beschreibung'],
                $this->formatCurrency($payment['betrag'])
            );
        }

        $lines[] = self::SUB_SEPARATOR;
        $lines[] = \sprintf('%-58s %15s', 'JAHRESSUMME:', $this->formatCurrency($data['payments']['ist']));
        $lines[] = str_repeat('=', 70);

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatAccountDevelopment(array $data): string
    {
        // This would typically come from balance configuration
        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatWirtschaftsplan(array $data): string
    {
        // This would typically come from Wirtschaftsplan configuration
        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatTaxDeductible(array $data): string
    {
        $headers = $data['configuration']['section_headers'];
        $texts = $data['configuration']['standard_texts'];
        $tax = $data['tax_deductible'];

        $lines = [
            '',
            $headers['tax_deductible'],
            str_repeat('-', self::LINE_WIDTH),
            \sprintf('%-45s %15s %15s %20s %25s',
                'Handwerkerleistungen', 'Gesamtkosten', 'anrechenbar', 'Ihr Anteil (Kosten)', 'Ihr Anteil (anrechenbar)'
            ),
            str_repeat('-', self::LINE_WIDTH),
        ];

        foreach ($tax['items'] as $item) {
            $lines[] = \sprintf('%-45s %15s %15s %20s %25s',
                $item['kostenkonto'] . ' - ' . $item['beschreibung'] . ' ' . $item['verteilungsschluessel'],
                $this->formatCurrency($item['gesamtkosten']),
                $this->formatCurrency($item['anrechenbar']),
                $this->formatCurrency($item['anteil_kosten']),
                $this->formatCurrency($item['anteil_anrechenbar'])
            );
        }

        $lines[] = str_repeat('-', self::LINE_WIDTH);
        $lines[] = \sprintf('%-45s %15s %15s %20s %25s',
            'SUMME Handwerkerleistungen',
            $this->formatCurrency(array_sum(array_column($tax['items'], 'gesamtkosten'))),
            $this->formatCurrency(array_sum(array_column($tax['items'], 'anrechenbar'))),
            $this->formatCurrency(array_sum(array_column($tax['items'], 'anteil_kosten'))),
            $this->formatCurrency($tax['total_anrechenbar'])
        );
        $lines[] = str_repeat('=', self::LINE_WIDTH);

        $lines[] = '';
        $lines[] = \sprintf($texts['tax_deductible_info'], $tax['total_anrechenbar']);
        $lines[] = '';

        foreach ($texts['tax_notice'] as $line) {
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatFooter(array $data): string
    {
        $headers = $data['configuration']['section_headers'];

        return implode("\n", [
            '',
            self::SEPARATOR_LINE,
            $headers['end'],
            self::SEPARATOR_LINE,
        ]);
    }
}
