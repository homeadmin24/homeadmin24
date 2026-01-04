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

            // Use centralized calculated_totals from HgaService (BGH V ZR 44/09 compliant)
            $calc = $data['calculated_totals'];
            $saldo = $calc['balance']['saldo'];
            $abrechnungsspitze = $calc['balance']['abrechnungsspitze'];
            $zahlungsdifferenz = $calc['balance']['zahlungsdifferenz'];

            $wegGesamtkosten = $calc['weg']['gesamtkosten'];
            $unitGesamtkosten = $calc['unit']['gesamtkosten'];

            $content[] = \sprintf('✅ ERGEBNIS: %s von %.2f €',
                $saldo > 0 ? 'NACHZAHLUNG' : 'GUTHABEN', abs($saldo));
            $content[] = '';

            $content[] = 'BERECHNUNG DES ANTEILS:';
            $content[] = '--------------------------------------------------';
            $content[] = 'Position                       Objekt gesamt   Ihr Anteil';
            $content[] = '--------------------------------------------------';

            $wegTotalSoll = $data['weg_totals']['soll'] ?? 0.0;
            $wegTotalIst = $data['weg_totals']['ist'] ?? 0.0;
            $unitSoll = $data['payments']['soll'] ?? 0.0;
            $unitIst = $data['payments']['ist'] ?? 0.0;

            // Calculate WEG balance components
            $wegAbrechnungsspitze = $wegGesamtkosten - $wegTotalSoll;
            $wegZahlungsdifferenz = $wegTotalIst - $wegTotalSoll;

            $content[] = \sprintf('Gesamtkosten                     %8.2f €    %8.2f €',
                $wegGesamtkosten, $unitGesamtkosten);
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

        // Use centralized umlageschluessel data from HgaService
        if (isset($data['umlageschluessel'])) {
            foreach ($data['umlageschluessel'] as $schluessel) {
                $nummer = $schluessel['nummer'];
                $bezeichnung = $schluessel['bezeichnung'];
                $umlageTyp = $schluessel['umlage_typ'];
                $zeitraum = $schluessel['zeitraum'];
                $tage = $schluessel['tage'];
                $gesamtumlage = $schluessel['gesamtumlage'];
                $anteil = $schluessel['anteil'];

                // Format gesamtumlage
                if (is_string($gesamtumlage)) {
                    $gesamtumlageStr = $gesamtumlage;
                } elseif (is_numeric($gesamtumlage)) {
                    $gesamtumlageStr = number_format($gesamtumlage, 2, ',', '.');
                } else {
                    $gesamtumlageStr = '';
                }

                // Format anteil
                if ($anteil === null) {
                    $anteilStr = '';
                } elseif ($nummer === '05*') {
                    $anteilStr = number_format($anteil, 4, ',', '.');
                } else {
                    $anteilStr = number_format($anteil, 2, ',', '.');
                }

                $content[] = \sprintf('%-4s %-30s %-12s %d     %d   %-15s %-15s',
                    $nummer, $bezeichnung, $umlageTyp, $zeitraum, $tage, $gesamtumlageStr, $anteilStr);
            }
        }

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

                if (isset($data['external_costs'])) {
                    $heatingTotal = $data['external_costs']['heating']['total'] ?? 0.0;
                    $heatingAnteil = $data['external_costs']['heating']['unit_share'] ?? 0.0;
                    $waterTotal = $data['external_costs']['water']['total'] ?? 0.0;
                    $waterAnteil = $data['external_costs']['water']['unit_share'] ?? 0.0;

                    $heizungWasserTotal = $heatingTotal + $waterTotal;
                    $heizungWasserAnteil = $heatingAnteil + $waterAnteil;

                    $content[] = \sprintf('ext. berechn. Heiz-/Wasserkosten 01*     %8.2f €   %8.2f €',
                        $heizungWasserTotal, $heizungWasserAnteil);
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

        // Use centralized calculated_totals from HgaService (BGH V ZR 44/09 compliant)
        // Note: Rücklagen are shown in ZAHLUNGSÜBERSICHT, not here (BGH V ZR 44/09)
        $calc = $data['calculated_totals'];

        $content[] = \sprintf('∑ GESAMTSUMME ALLER KOSTEN     %8.2f €   %8.2f €',
            $calc['weg']['gesamtkosten'], $calc['unit']['gesamtkosten']);
        $content[] = '';

        // Add payment overview section
        if (isset($data['payments']['monthly_unit_payments'])) {
            $monthlyUnitPayments = $data['payments']['monthly_unit_payments'];
            $monthlyWegIst = $data['payments']['weg_totals']['monthly_weg_ist'] ?? [];

            $content[] = 'ZAHLUNGSÜBERSICHT ' . $data['year'] . ':';
            $content[] = '----------------------------------------------------------------------';
            $content[] = 'Debitor: ' . $data['einheit']['nummer'] . ' ' . $data['einheit']['beschreibung'] . ' ' . $data['einheit']['eigentuemer'];
            $content[] = 'Zeitraum: 01.01.' . $data['year'] . ' - 31.12.' . $data['year'];
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('%-12s %-20s %15s %15s', 'Datum', 'Beschreibung', 'WEG Gesamt', 'Ihre Zahlung');
            $content[] = '----------------------------------------------------------------------';

            // Show monthly wohngeld payments
            $totalWohngeldUnit = 0.0;
            $totalWohngeldWeg = 0.0;
            for ($month = 1; $month <= 12; ++$month) {
                if (isset($monthlyUnitPayments[$month]) && $monthlyUnitPayments[$month]['wohngeld'] > 0) {
                    $wegTotal = $monthlyWegIst[$month] ?? 0.0;
                    $unitTotal = $monthlyUnitPayments[$month]['wohngeld'];

                    $content[] = \sprintf('%02d.%d       %-20s %14s € %14s €',
                        $month,
                        $data['year'],
                        'Wohngeld',
                        number_format($wegTotal, 2, ',', '.'),
                        number_format($unitTotal, 2, ',', '.')
                    );

                    $totalWohngeldUnit += $unitTotal;
                    $totalWohngeldWeg += $wegTotal;
                }
            }

            $content[] = '----------------------------------------------------------------';
            $content[] = \sprintf('%-33s %14s € %14s €',
                '',
                number_format($totalWohngeldWeg, 2, ',', '.'),
                number_format($totalWohngeldUnit, 2, ',', '.')
            );

            // Collect other payments from all months
            $otherPayments = [];
            foreach ($monthlyUnitPayments as $monthData) {
                if (isset($monthData['other'])) {
                    $otherPayments = array_merge($otherPayments, $monthData['other']);
                }
            }

            // Show other payments (Nachzahlungen, Sonderumlagen)
            $totalOtherUnit = 0.0;
            if (!empty($otherPayments)) {
                $content[] = '';
                $totalOtherWeg = 0.0;
                $wegCategoryTotals = $data['payments']['weg_category_totals'] ?? [];
                $unitCategoryTotals = [];

                foreach ($otherPayments as $payment) {
                    $kategorie = $payment['kategorie'] ?? 'Unbekannt';
                    $unitCategoryTotals[$kategorie] = ($unitCategoryTotals[$kategorie] ?? 0.0) + $payment['betrag'];
                }

                foreach ($unitCategoryTotals as $kategorie => $unitTotal) {
                    $wegTotal = $wegCategoryTotals[$kategorie] ?? 0.0;
                    $content[] = \sprintf('%-12s %-20s %14s € %14s €',
                        (string) $data['year'],
                        $kategorie,
                        number_format($wegTotal, 2, ',', '.'),
                        number_format($unitTotal, 2, ',', '.')
                    );
                    $totalOtherUnit += $unitTotal;
                    $totalOtherWeg += $wegTotal;
                }
                $content[] = '----------------------------------------------------------------------';
                $content[] = \sprintf('%-33s %14s € %14s €',
                    '',
                    number_format($totalOtherWeg, 2, ',', '.'),
                    number_format($totalOtherUnit, 2, ',', '.')
                );
            }

            $totalPayments = $totalWohngeldUnit + $totalOtherUnit;
            $content[] = '';
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('%-49s %14s €',
                '',
                number_format($totalPayments, 2, ',', '.')
            );

            $content[] = '';
            $content[] = '======================================================================';

            // Add WEG totals summary (only Wohngeld)
            $wegPayments = $data['payments']['weg_totals']['ist'] ?? 0.0;
            $content[] = '';
            $content[] = 'WEG GESAMTSUMMEN:';
            $content[] = \sprintf('Wohngeld gesamt (WEG):                                 %s €',
                number_format($wegPayments, 2, ',', '.')
            );
            $content[] = '======================================================================';

            // Add Rücklagenzuführung with WEG and Unit totals
            $unitRuecklagen = 0.0;
            $wegRuecklagen = $data['payments']['weg_totals']['ruecklagen'] ?? 0.0;
            if (isset($data['costs']['ruecklagen']['items']) && !empty($data['costs']['ruecklagen']['items'])) {
                $content[] = '';
                $content[] = 'RÜCKLAGENZUFÜHRUNG (Einnahmen für Rücklage):';
                $content[] = '----------------------------------------------------------------------';
                foreach ($data['costs']['ruecklagen']['items'] as $item) {
                    $unitRuecklagen += abs($item['anteil']);
                }
                $content[] = \sprintf('31.12.%d     %-26s %14s € %14s €',
                    $data['year'],
                    'Rücklagenzuführung',
                    number_format(abs($wegRuecklagen), 2, ',', '.'),
                    number_format($unitRuecklagen, 2, ',', '.')
                );
                $content[] = '----------------------------------------------------------------------';
            }

            $content[] = '';
        }

        // Add balance development section
        if (isset($data['balance']) && $data['balance']['hasData']) {
            $balance = $data['balance'];
            $wegRuecklagen = $data['payments']['weg_totals']['ruecklagen'] ?? 0.0;

            // Calculate separate balances for Hausgeld and Rücklagen
            $startTotal = $balance['startAmount'];
            $endTotal = $balance['endAmount'];
            $endRuecklagen = abs($wegRuecklagen);
            $endHausgeld = $endTotal - $endRuecklagen;

            // Assume all previous balance was Hausgeld
            $startHausgeld = $startTotal;
            $startRuecklagen = 0.0;

            $content[] = 'KONTOSTANDSENTWICKLUNG ' . $data['year'] . ':';
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Kontostand Hausgeld 31.12.%d:       %s €',
                $balance['year'] - 1,
                number_format($startHausgeld, 2, ',', '.')
            );
            $content[] = \sprintf('Kontostand Rücklagen 31.12.%d:      %s €',
                $balance['year'] - 1,
                number_format($startRuecklagen, 2, ',', '.')
            );
            $content[] = '';
            $content[] = \sprintf('Kontostand Hausgeld 31.12.%d:       %s €',
                $balance['year'],
                number_format($endHausgeld, 2, ',', '.')
            );
            $content[] = \sprintf('Kontostand Rücklagen 31.12.%d:      %s €',
                $balance['year'],
                number_format($endRuecklagen, 2, ',', '.')
            );
            $content[] = '';
            $content[] = \sprintf('Veränderung Hausgeld:               %s%s €',
                ($endHausgeld - $startHausgeld) < 0 ? '' : '+',
                number_format($endHausgeld - $startHausgeld, 2, ',', '.')
            );
            $content[] = \sprintf('Veränderung Rücklagen:              %s%s €',
                ($endRuecklagen - $startRuecklagen) < 0 ? '' : '+',
                number_format($endRuecklagen - $startRuecklagen, 2, ',', '.')
            );
            $content[] = '======================================================================';
            $content[] = '';
        }

        // Add Wirtschaftsplan section
        if (isset($data['wirtschaftsplan'])) {
            $wirtschaftsplan = $data['wirtschaftsplan'];
            $mea = $this->extractMEAAsDecimal($data['einheit']['mea'] ?? '');
            $year = $data['year'];
            $planYear = $year + 1; // Wirtschaftsplan is for the NEXT year

            $content[] = \sprintf('VERMÖGENSÜBERSICHT UND WIRTSCHAFTSPLAN %d', $planYear);
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
            $content[] = \sprintf('WIRTSCHAFTSPLAN %d - GEPLANTE AUSGABEN:', $planYear);
            $content[] = '----------------------------------------------------------------------';
            $content[] = '1. UMLAGEFÄHIGE KOSTEN (auf Mieter umlegbar):';
            $content[] = '----------------------------------------------------------------------';

            $umlagefaehigTotal = 0.0;
            foreach ($wirtschaftsplan['planned_expenses']['umlagefaehig'] as $expense) {
                $name = $expense['name'] ?? 'Unbekannt';
                $amount = $expense['amount'] ?? 0.0;
                $content[] = \sprintf('%-50s %15s €', $name, number_format($amount, 2, ',', '.'));
                $umlagefaehigTotal += $amount;
            }
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Zwischensumme umlagefähig:                          %s €',
                number_format($umlagefaehigTotal, 2, ',', '.'));
            $content[] = '';

            $content[] = '2. NICHT UMLAGEFÄHIGE KOSTEN:';
            $content[] = '----------------------------------------------------------------------';

            $nichtUmlagefaehigTotal = 0.0;
            foreach ($wirtschaftsplan['planned_expenses']['nicht_umlagefaehig'] as $expense) {
                $name = $expense['name'] ?? 'Unbekannt';
                $amount = $expense['amount'] ?? 0.0;
                $content[] = \sprintf('%-50s %15s €', $name, number_format($amount, 2, ',', '.'));
                $nichtUmlagefaehigTotal += $amount;
            }
            $content[] = '----------------------------------------------------------------------';
            $content[] = \sprintf('Zwischensumme nicht umlagefähig:                     %s €',
                number_format($nichtUmlagefaehigTotal, 2, ',', '.'));
            $content[] = '';

            $gesamtausgaben = $umlagefaehigTotal + $nichtUmlagefaehigTotal;
            $content[] = \sprintf('GESAMTAUSGABEN %d:                                 %s €',
                $planYear, number_format($gesamtausgaben, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            // Geplante Einnahmen
            $content[] = \sprintf('GEPLANTE EINNAHMEN %d:', $planYear);
            $content[] = '----------------------------------------------------------------------';
            $monthlyTotal = $wirtschaftsplan['planned_income']['monthly_total'] ?? 1500.0;
            $previousYear = $year; // For Wirtschaftsplan 2026, we show Nachzahlungen from 2025
            $nachzahlungenKey = 'nachzahlungen_' . $previousYear;
            $nachzahlungen = $wirtschaftsplan['planned_income'][$nachzahlungenKey] ?? 0.0;
            $yearlyVorschuesse = $monthlyTotal * 12;

            $content[] = \sprintf('Hausgeld-Vorschüsse (12 × %s €)                %s €',
                number_format($monthlyTotal, 2, ',', '.'), number_format($yearlyVorschuesse, 2, ',', '.'));
            $content[] = \sprintf('Nachzahlungen aus HGA %d                                %s €',
                $previousYear, number_format($nachzahlungen, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';

            $gesamteinnahmen = $yearlyVorschuesse + $nachzahlungen;
            $content[] = \sprintf('GESAMTEINNAHMEN %d:                                    %s €',
                $planYear, number_format($gesamteinnahmen, 2, ',', '.'));
            $content[] = '----------------------------------------------------------------------';
            $saldo = $gesamteinnahmen - $gesamtausgaben;
            $content[] = \sprintf('SALDO (Einnahmen - Ausgaben):                             %s €',
                number_format($saldo, 2, ',', '.'));
            $content[] = '======================================================================';
            $content[] = '';

            // Hausgeld-Vorschuss für Owner
            $meaPercent = round($mea * 100);
            $monthlyOwnerShare = $monthlyTotal * $mea;

            $content[] = \sprintf('HAUSGELD-VORSCHUSS %d - IHR ANTEIL:', $planYear);
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
