<?php

declare(strict_types=1);

namespace App\Service\Hga\Report;

use App\Entity\WegEinheit;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\ReportGeneratorInterface;

/**
 * TXT report generator for HGA.
 *
 * Generates plain text reports compatible with the existing format.
 */
class TxtReportGenerator implements ReportGeneratorInterface
{
    public function __construct(
        private HgaServiceInterface $hgaService,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generateReport(WegEinheit $einheit, int $year, array $options = []): string
    {
        $data = $this->hgaService->generateReportData($einheit, $year);

        return $this->generateTxtContent($data);
    }

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
     */
    public function validateInputs(WegEinheit $einheit, int $year, array $options = []): array
    {
        return $this->hgaService->validateCalculationInputs($einheit, $year);
    }

    /**
     * Generate TXT content from data.
     *
     * @param array<string, mixed> $data
     */
    private function generateTxtContent(array $data): string
    {
        $content = [];

        // Initialize totals that will be used in multiple sections
        $wegTotalCosts = 0.0;
        $unitTotalCosts = 0.0;

        $content[] = '================================================================================';
        $content[] = 'HAUSGELDABRECHNUNG ' . $data['year'] . ' - EINZELABRECHNUNG';
        $content[] = '================================================================================';
        $content[] = '';
        $content[] = 'Erstellung: ' . $data['calculation_date']->format('d.m.Y');
        $content[] = 'Objekt: ' . $data['weg']['name'] . ', ' . $data['weg']['adresse'];
        $content[] = 'Abrechnungszeitraum: 01.01.' . $data['year'] . ' - 31.12.' . $data['year'];
        $content[] = '';
        $content[] = 'EIGENTÜMER INFORMATION:';
        $content[] = '----------------------------------------';
        $content[] = 'Eigentümer: ' . $data['einheit']['nummer'] . ' ' . $data['einheit']['beschreibung'] . ' ' . $data['einheit']['eigentuemer'];
        $content[] = '';

        // Add balance calculation section - moved to top to match reference
        if (isset($data['payments'])) {
            $content[] = 'ABRECHNUNGSÜBERSICHT:';
            $content[] = '----------------------------------------';

            // Calculate totals that include external costs (same as final GESAMTSUMME)
            $heizungWasserTotal = 0.0;
            $heizungWasserAnteil = 0.0;
            if (isset($data['external_costs'])) {
                $heizungWasserTotal = ($data['external_costs']['heating']['total'] ?? 0.0) +
                                    ($data['external_costs']['water']['total'] ?? 0.0);
                $heizungWasserAnteil = ($data['external_costs']['heating']['unit_share'] ?? 0.0) +
                                     ($data['external_costs']['water']['unit_share'] ?? 0.0);
            }

            $sonstigeTotal = array_sum(array_column($data['costs']['umlagefaehig']['items'] ?? [], 'total'));
            $sonstigeAnteil = array_sum(array_column($data['costs']['umlagefaehig']['items'] ?? [], 'anteil'));

            $wegUmlagefaehig = $heizungWasserTotal + $sonstigeTotal;
            $wegNichtUmlagefaehig = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'] ?? [], 'total'));
            $wegRuecklagen = -abs(array_sum(array_column($data['costs']['ruecklagen']['items'] ?? [], 'total')));
            $wegTotalCosts = $wegUmlagefaehig + $wegNichtUmlagefaehig + $wegRuecklagen;

            $unitUmlagefaehig = $heizungWasserAnteil + $sonstigeAnteil;
            $unitNichtUmlagefaehig = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'] ?? [], 'anteil'));
            $unitRuecklagen = -abs(array_sum(array_column($data['costs']['ruecklagen']['items'] ?? [], 'anteil')));
            $unitTotalCosts = $unitUmlagefaehig + $unitNichtUmlagefaehig + $unitRuecklagen;

            $unitSoll = $data['payments']['soll'] ?? 0.0;
            $unitIst = $data['payments']['ist'] ?? 0.0;
            $abrechnungsspitze = $unitTotalCosts - $unitSoll;
            $zahlungsdifferenz = $unitIst - $unitSoll;
            // Simple calculation: Total costs - Actual payments = Net balance
            $saldo = $unitTotalCosts - $unitIst;

            $content[] = \sprintf('✅ ERGEBNIS: %s von %.2f €',
                $saldo > 0 ? 'NACHZAHLUNG' : 'GUTHABEN', abs($saldo));
            $content[] = '';

            $content[] = 'BERECHNUNG DES ANTEILS:';
            $content[] = '--------------------------------------------------';
            $content[] = 'Position                       Objekt gesamt   Ihr Anteil';
            $content[] = '--------------------------------------------------';

            $wegTotalSoll = $data['weg_totals']['soll'] ?? 0.0;
            $wegTotalIst = $data['weg_totals']['ist'] ?? 0.0;

            // Calculate balance components using consistent totals
            $wegAbrechnungsspitze = $wegTotalCosts - $wegTotalSoll;
            $wegZahlungsdifferenz = $wegTotalIst - $wegTotalSoll;

            $content[] = \sprintf('Gesamtkosten                     %8.2f €    %8.2f €',
                $wegTotalCosts, $unitTotalCosts);
            $content[] = \sprintf('- Hausgeld-Vorschuss Soll        %8.2f €    %8.2f €',
                $wegTotalSoll, $unitSoll);
            $content[] = '--------------------------------------------------';
            $content[] = \sprintf('= Abrechnungsspitze              %8.2f €    %8.2f € (%s)',
                $wegAbrechnungsspitze, $abrechnungsspitze,
                $abrechnungsspitze > 0 ? 'Unterdeckung' : 'Überdeckung');
            $content[] = \sprintf('Hausgeld-Vorschuss Soll          %8.2f €    %8.2f €',
                $wegTotalSoll, $unitSoll);
            $content[] = \sprintf('- Hausgeld-Vorschuss Ist         %8.2f €    %8.2f €',
                $wegTotalIst, $unitIst);
            $content[] = '--------------------------------------------------';
            $content[] = \sprintf('= Zahlungsdifferenz              %8.2f €   %8.2f € (%s)',
                $wegZahlungsdifferenz, $zahlungsdifferenz,
                $zahlungsdifferenz >= 0 ? 'Überdeckung' : 'Unterdeckung');
            $content[] = \sprintf('= ABRECHNUNGS-SALDO                             %8.2f € (%s)',
                abs($saldo), $saldo > 0 ? 'Nachzahlung' : 'Guthaben');
            $content[] = '==================================================';
            $content[] = '';
        }

        // Add UMLAGESCHLÜSSEL section
        $content[] = 'UMLAGESCHLÜSSEL:';
        $content[] = '------------------------------------------------------------------------------------------------------------------------';
        $content[] = 'Nr.  Umlageschlüssel               Umlage       Zeitraum Tage  Gesamtumlage    Ihr Anteil     ';
        $content[] = '------------------------------------------------------------------------------------------------------------------------';

        // Unit specific values
        $mea = $this->extractMEAAsDecimal($data['einheit']['mea'] ?? '');
        $unitCount = 4.0; // Fixed for this WEG
        $hebeanlageShare = ($data['einheit']['hebeanlage'] ?? false) ? 1.0 : 0.0;

        $content[] = \sprintf('01*  ext. berechn. Heizkosten       € Festbetrag %d     365   Beträge siehe Ergebnisliste                ', $data['year']);
        $content[] = \sprintf('02*  ext. berechn. Wasser-/sonst. Kosten € Festbetrag %d     365   Beträge siehe Ergebnisliste                ', $data['year']);
        $content[] = \sprintf('03*  Anzahl Einheit                 Einheiten-anteilig %d     365   %.2f            %.2f           ', $data['year'], $unitCount, 1.0);
        $content[] = \sprintf('04*  Festumlage                     € Festbetrag %d     365   Beträge siehe Ergebnisliste                ', $data['year']);
        $content[] = \sprintf('05*  Miteigentumsanteil             Anzahl anteilig %d     365   1.000,000       %.4f       ', $data['year'], $mea * 1000);
        $content[] = \sprintf('06*  Hebeanlage                     Spezial      %d     365   6,00            %.2f           ', $data['year'], $hebeanlageShare);
        $content[] = '========================================================================================================================';
        $content[] = '';

        // Initialize cost totals
        $heizungWasserTotal = 0.0;
        $heizungWasserAnteil = 0.0;
        $sonstigeTotal = 0.0;
        $sonstigeAnteil = 0.0;

        // Add cost breakdown
        if (isset($data['costs']['umlagefaehig']['items'])) {
            $content[] = '1. UMLAGEFÄHIGE KOSTEN (Mieter):';
            $content[] = '--------------------------------------------------';
            $content[] = 'Kostenart                           Gesamtkosten   Ihr Anteil';
            $content[] = '--------------------------------------------------';

            if (isset($data['external_costs'])) {
                $content[] = 'HEIZUNG/WASSER/ABRECHNUNG:';

                // External heating costs
                if (isset($data['external_costs']['heating'])) {
                    $heatingTotal = $data['external_costs']['heating']['total'] ?? 0.0;
                    $heatingAnteil = $data['external_costs']['heating']['unit_share'] ?? 0.0;
                    $content[] = \sprintf('ext. berechn. Heizkosten 01*          %8.2f €   %8.2f €',
                        $heatingTotal, $heatingAnteil);
                    $heizungWasserTotal += $heatingTotal;
                    $heizungWasserAnteil += $heatingAnteil;
                }

                // External water costs
                if (isset($data['external_costs']['water'])) {
                    $waterTotal = $data['external_costs']['water']['total'] ?? 0.0;
                    $waterAnteil = $data['external_costs']['water']['unit_share'] ?? 0.0;
                    $content[] = \sprintf('ext. berechn. Wasser-/sonst. Kosten 02*   %8.2f €     %8.2f €',
                        $waterTotal, $waterAnteil);
                    $heizungWasserTotal += $waterTotal;
                    $heizungWasserAnteil += $waterAnteil;
                }

                if ($heizungWasserTotal > 0) {
                    $content[] = \sprintf('∑ Zwischensumme: Heizung/Wasser/Abrechnung    %8.2f €   %8.2f €',
                        $heizungWasserTotal, $heizungWasserAnteil);
                    $content[] = '';
                }
            }

            // Add regular umlagefähige costs

            if (!empty($data['costs']['umlagefaehig']['items'])) {
                $content[] = 'SONSTIGE UMLAGEFÄHIGE KOSTEN:';

                foreach ($data['costs']['umlagefaehig']['items'] as $item) {
                    $content[] = \sprintf('%s - %s %s      %8.2f €      %8.2f €',
                        $item['kostenkonto'],
                        $item['beschreibung'],
                        $item['verteilungsschluessel'] ?? '05*',
                        $item['total'],
                        $item['anteil']
                    );
                    $sonstigeTotal += $item['total'];
                    $sonstigeAnteil += $item['anteil'];
                }

                $content[] = \sprintf('∑ Zwischensumme: Sonstige         %8.2f €   %8.2f €',
                    $sonstigeTotal, $sonstigeAnteil);
            }

            $content[] = '--------------------------------------------------';
            $content[] = \sprintf('∑ SUMME: umlagefähig (Mieter)   %8.2f €   %8.2f €',
                $heizungWasserTotal + $sonstigeTotal, $heizungWasserAnteil + $sonstigeAnteil);
            $content[] = '==================================================';
            $content[] = '';
        }

        if (isset($data['costs']['nicht_umlagefaehig']['items'])) {
            $content[] = '2. NICHT UMLAGEFÄHIGE KOSTEN (Mieter):';
            $content[] = '--------------------------------------------------';
            $content[] = 'Kostenart                           Gesamtkosten   Ihr Anteil';
            $content[] = '--------------------------------------------------';
            foreach ($data['costs']['nicht_umlagefaehig']['items'] as $item) {
                $content[] = \sprintf('%s - %s %s      %8.2f €      %8.2f €',
                    $item['kostenkonto'],
                    $item['beschreibung'],
                    $item['verteilungsschluessel'] ?? '05*',
                    $item['total'],
                    $item['anteil']
                );
            }
            $content[] = '--------------------------------------------------';

            // Calculate totals for nicht umlagefähig
            $nichtUmlagefaehigWegTotal = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'], 'total'));
            $nichtUmlagefaehigUnitTotal = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'], 'anteil'));

            $content[] = \sprintf('∑ Zwischensumme: Sonstige     %8.2f €   %8.2f €',
                $nichtUmlagefaehigWegTotal, $nichtUmlagefaehigUnitTotal);
            $content[] = '';
            $content[] = \sprintf('∑ SUMME: nicht umlagefähig (Mieter) %8.2f €   %8.2f €',
                $nichtUmlagefaehigWegTotal, $nichtUmlagefaehigUnitTotal);
            $content[] = '==================================================';
            $content[] = '';
        }

        // Add Rücklagenzuführung section
        if (isset($data['costs']['ruecklagen']['items']) && !empty($data['costs']['ruecklagen']['items'])) {
            $content[] = '3. RÜCKLAGENZUFÜHRUNG:';
            $content[] = '--------------------------------------------------';
            $content[] = 'Art der Rücklagenzuführung        Gesamtbetrag   Ihr Anteil';
            $content[] = '--------------------------------------------------';
            foreach ($data['costs']['ruecklagen']['items'] as $item) {
                $content[] = \sprintf('%s - %s %s      %8.2f €    %8.2f €',
                    $item['kostenkonto'],
                    $item['beschreibung'],
                    $item['verteilungsschluessel'] ?? '05*',
                    -abs($item['total']),  // Show as negative (contribution TO reserves)
                    -abs($item['anteil'])  // Show as negative (reduces owner's cost)
                );
            }
            $content[] = '--------------------------------------------------';

            // Calculate totals for Rücklagenzuführung
            $ruecklagenWegTotal = array_sum(array_column($data['costs']['ruecklagen']['items'], 'total'));
            $ruecklagenUnitTotal = array_sum(array_column($data['costs']['ruecklagen']['items'], 'anteil'));

            $content[] = \sprintf('∑ SUMME: Rücklagenzuführung       %8.2f €    %8.2f €',
                -abs($ruecklagenWegTotal), -abs($ruecklagenUnitTotal));
            $content[] = '==================================================';
            $content[] = '';
            $content[] = '';
        }

        // Calculate final totals using the displayed section totals to ensure consistency
        $finalWegUmlagefaehig = ($heizungWasserTotal + $sonstigeTotal);
        $finalUnitUmlagefaehig = ($heizungWasserAnteil + $sonstigeAnteil);

        $finalWegNichtUmlagefaehig = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'] ?? [], 'total'));
        $finalUnitNichtUmlagefaehig = array_sum(array_column($data['costs']['nicht_umlagefaehig']['items'] ?? [], 'anteil'));

        $finalWegRuecklagen = -abs(array_sum(array_column($data['costs']['ruecklagen']['items'] ?? [], 'total')));
        $finalUnitRuecklagen = -abs(array_sum(array_column($data['costs']['ruecklagen']['items'] ?? [], 'anteil')));

        $finalWegTotal = $finalWegUmlagefaehig + $finalWegNichtUmlagefaehig + $finalWegRuecklagen;
        $finalUnitTotal = $finalUnitUmlagefaehig + $finalUnitNichtUmlagefaehig + $finalUnitRuecklagen;

        $content[] = \sprintf('∑ GESAMTSUMME ALLER KOSTEN     %8.2f €   %8.2f €',
            $finalWegTotal, $finalUnitTotal);
        $content[] = '';

        // Add payment overview section
        if (isset($data['payments']['payment_details']) && !empty($data['payments']['payment_details'])) {
            $content[] = 'ZAHLUNGSÜBERSICHT ' . $data['year'] . ':';
            $content[] = '----------------------------------------------------------------------';
            $content[] = 'Debitor: ' . $data['einheit']['nummer'] . ' ' . $data['einheit']['beschreibung'] . ' ' . $data['einheit']['eigentuemer'];
            $content[] = 'Zeitraum: 01.01.' . $data['year'] . ' - 31.12.' . $data['year'];
            $content[] = '----------------------------------------------------------------------';
            $content[] = 'Datum        Beschreibung                                   Betrag';
            $content[] = '----------------------------------------------------------------------';

            $totalPayments = 0.0;
            foreach ($data['payments']['payment_details'] as $payment) {
                $datum = $payment['datum']->format('d.m.Y');

                // Clean up description for display
                $beschreibung = $payment['beschreibung'];
                $beschreibung = str_replace('WOHNGELD MUSTERMANN/BEISPIEL', 'Wohngeld', $beschreibung);
                $beschreibung = str_replace('SONDERUMLAGE HEBEANLAGE BEISPIEL', 'Sonderumlage', $beschreibung);
                $beschreibung = str_replace('Nachzahlung 2023', 'Zahlung', $beschreibung);

                // Truncate if too long
                if (mb_strlen($beschreibung) > 45) {
                    $beschreibung = mb_substr($beschreibung, 0, 42) . '...';
                }

                $content[] = \sprintf('%-12s %-45s %10s €',
                    $datum,
                    $beschreibung,
                    number_format($payment['betrag'], 2, ',', '.')
                );

                $totalPayments += $payment['betrag'];
            }

            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('JAHRESSUMME:                                            %s €',
                number_format($totalPayments, 2, ',', '.')
            );
            $content[] = '======================================================================';
            $content[] = '';
        }

        // Add balance development section
        if (isset($data['balance']) && $data['balance']['hasData']) {
            $balance = $data['balance'];
            $content[] = 'KONTOSTANDSENTWICKLUNG ' . $data['year'] . ':';
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Kontostand 31.12.%d:                %s €',
                $balance['year'] - 1,
                number_format($balance['startAmount'], 2, ',', '.')
            );
            $content[] = \sprintf('Kontostand 31.12.%d:                %s €',
                $balance['year'],
                number_format($balance['endAmount'], 2, ',', '.')
            );
            $content[] = \sprintf('Veränderung:                        %s%s €',
                $balance['change'] < 0 ? '' : '+',
                number_format($balance['change'], 2, ',', '.')
            );
            $content[] = '======================================================================';
            $content[] = '';
        }

        // Add Wirtschaftsplan section
        if (isset($data['wirtschaftsplan'])) {
            $wirtschaftsplan = $data['wirtschaftsplan'];
            $mea = $this->extractMEAAsDecimal($data['einheit']['mea'] ?? '');

            $content[] = 'VERMÖGENSÜBERSICHT UND WIRTSCHAFTSPLAN 2025';
            $content[] = str_repeat('=', 80);
            $content[] = '';

            // Vermögensstand
            $content[] = 'VERMÖGENSSTAND:';
            $content[] = '----------------------------------------------------------------------';
            $hausgeldKonto = $wirtschaftsplan['bank_balances']['hausgeld_konto'] ?? 0.0;
            $ruecklagenKonto = $wirtschaftsplan['bank_balances']['ruecklagen_konto'] ?? 0.0;
            $balanceDate = $wirtschaftsplan['bank_balances']['balance_date'] ?? '16.07.2025';
            $gesamtvermoegen = $hausgeldKonto + $ruecklagenKonto;

            $content[] = \sprintf('Kontostand WEG-Hausgeldkonto (%s):                %s €',
                $balanceDate, number_format($hausgeldKonto, 2, ',', '.'));
            $content[] = \sprintf('Kontostand Rücklagenkonto (%s):                      %s €',
                $balanceDate, number_format($ruecklagenKonto, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('GESAMTVERMÖGEN WEG:                                      %s €',
                number_format($gesamtvermoegen, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            // Wirtschaftsplan - Ausgaben
            $content[] = 'WIRTSCHAFTSPLAN 2025 - GEPLANTE AUSGABEN:';
            $content[] = '----------------------------------------------------------------------';
            $content[] = '1. UMLAGEFÄHIGE KOSTEN (auf Mieter umlegbar):';
            $content[] = '----------------------------------------------------------------------';

            $umlagefaehigTotal = 0.0;
            foreach ($wirtschaftsplan['planned_expenses']['umlagefaehig'] as $kategorie => $betrag) {
                $content[] = \sprintf('%-50s %15s €', $kategorie, number_format($betrag, 2, ',', '.'));
                $umlagefaehigTotal += $betrag;
            }
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Zwischensumme umlagefähig:                          %s €',
                number_format($umlagefaehigTotal, 2, ',', '.'));
            $content[] = '';

            $content[] = '2. NICHT UMLAGEFÄHIGE KOSTEN:';
            $content[] = '----------------------------------------------------------------------';

            $nichtUmlagefaehigTotal = 0.0;
            foreach ($wirtschaftsplan['planned_expenses']['nicht_umlagefaehig'] as $kategorie => $betrag) {
                $content[] = \sprintf('%-50s %15s €', $kategorie, number_format($betrag, 2, ',', '.'));
                $nichtUmlagefaehigTotal += $betrag;
            }
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Zwischensumme nicht umlagefähig:                     %s €',
                number_format($nichtUmlagefaehigTotal, 2, ',', '.'));
            $content[] = '';

            $gesamtausgaben = $umlagefaehigTotal + $nichtUmlagefaehigTotal;
            $content[] = \sprintf('GESAMTAUSGABEN 2025:                                 %s €',
                number_format($gesamtausgaben, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            // Geplante Einnahmen
            $content[] = 'GEPLANTE EINNAHMEN 2025:';
            $content[] = '----------------------------------------------------------------------';
            $monthlyTotal = $wirtschaftsplan['planned_income']['monthly_total'] ?? 1500.0;
            $nachzahlungen = $wirtschaftsplan['planned_income']['nachzahlungen_2024'] ?? 0.0;
            $yearlyVorschuesse = $monthlyTotal * 12;

            $content[] = \sprintf('Hausgeld-Vorschüsse (12 × %s €)                %s €',
                number_format($monthlyTotal, 2, ',', '.'), number_format($yearlyVorschuesse, 2, ',', '.'));
            $content[] = \sprintf('Nachzahlungen aus HGA 2024                                %s €',
                number_format($nachzahlungen, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';

            $gesamteinnahmen = $yearlyVorschuesse + $nachzahlungen;
            $content[] = \sprintf('GESAMTEINNAHMEN 2025:                                    %s €',
                number_format($gesamteinnahmen, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';
            $saldo = $gesamteinnahmen - $gesamtausgaben;
            $content[] = \sprintf('SALDO (Einnahmen - Ausgaben):                             %s €',
                number_format($saldo, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            // Hausgeld-Vorschuss für Owner
            $meaPercent = round($mea * 100);
            $monthlyOwnerShare = $monthlyTotal * $mea;

            $content[] = 'HAUSGELD-VORSCHUSS 2025 - IHR ANTEIL:';
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('                                           Gesamt WEG Ihr Anteil (%d%%)', $meaPercent);
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Monatlicher Vorschuss WEG gesamt:          %s €        %s €',
                number_format($monthlyTotal, 2, ',', '.'), number_format($monthlyOwnerShare, 2, ',', '.'));
            $content[] = \sprintf('Ihr NEUER monatlicher Vorschuss:                            %s €',
                number_format($monthlyOwnerShare, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Änderung pro Monat:                                          %s €',
                number_format(0.0, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            $standardTexts = $data['configuration']['standard_texts'] ?? [];
            if (isset($standardTexts['balance_notice'])) {
                $content[] = $standardTexts['balance_notice'];
                if (isset($standardTexts['balance_notice_2'])) {
                    $content[] = $standardTexts['balance_notice_2'];
                }
            }
            $content[] = '';
        }

        // Add tax deduction section
        if (isset($data['tax_deductible']['items']) && !empty($data['tax_deductible']['items'])) {
            $content[] = 'STEUERBEGÜNSTIGTE LEISTUNGEN nach §35a EStG:';
            $content[] = '------------------------------------------------------------------------------------------------------------------------';
            $content[] = 'Handwerkerleistungen                          Gesamtkosten   anrechenbar Ihr Anteil (Kosten) Ihr Anteil (anrechenbar)';
            $content[] = '------------------------------------------------------------------------------------------------------------------------';

            foreach ($data['tax_deductible']['items'] as $item) {
                $content[] = \sprintf('%-8s - %-25s %s %12.2f € %11.2f € %14.2f € %12.2f €',
                    $item['kostenkonto'],
                    $item['beschreibung'],
                    $item['verteilungsschluessel'],
                    $item['gesamtkosten'],
                    $item['anrechenbar'],
                    $item['anteil_kosten'],
                    $item['anteil_anrechenbar']
                );
            }

            $content[] = '------------------------------------------------------------------------------------------------------------------------';
            $content[] = \sprintf('SUMME Handwerkerleistungen                    %8.2f € %11.2f € %14.2f € %12.2f €',
                array_sum(array_column($data['tax_deductible']['items'], 'gesamtkosten')),
                array_sum(array_column($data['tax_deductible']['items'], 'anrechenbar')),
                array_sum(array_column($data['tax_deductible']['items'], 'anteil_kosten')),
                $data['tax_deductible']['total_anrechenbar']
            );
            $content[] = '========================================================================================================================';
            $content[] = '';
            $content[] = \sprintf('✅ Ihr steuerlich absetzbarer Betrag (100%% der Arbeits-/Fahrtkosten inkl. MwSt.): %.2f EUR',
                $data['tax_deductible']['total_anrechenbar']);
            $content[] = '';
            $content[] = 'HINWEIS: Diese Beträge können Sie in Ihrer Steuererklärung als haushaltsnahe';
            $content[] = 'Dienstleistungen geltend machen (20% davon, max. 1.200 EUR Steuerermäßigung pro Jahr).';
            $content[] = '';
        }

        $content[] = '================================================================================';
        $content[] = 'ENDE DER HAUSGELDABRECHNUNG';
        $content[] = '================================================================================';

        return implode("\n", $content);
    }

    /**
     * Extract MEA as decimal from string.
     */
    private function extractMEAAsDecimal(string $meaString): float
    {
        if (!$meaString) {
            return 0.0;
        }

        if (str_contains($meaString, '/')) {
            [$numerator, $denominator] = explode('/', $meaString, 2);

            if (0.0 === (float) $denominator) {
                return 0.0;
            }

            return (float) $numerator / (float) $denominator;
        }

        return (float) $meaString / 1000; // Assuming 1000 as total if no fraction
    }
}
