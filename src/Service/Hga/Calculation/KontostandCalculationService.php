<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\Weg;
use App\Repository\WegKontostandRepository;
use App\Repository\ZahlungRepository;

/**
 * Service for calculating WEG bank balance reconciliation (Vermögensabgrenzung).
 */
class KontostandCalculationService
{
    public function __construct(
        private WegKontostandRepository $kontostandRepository,
        private ZahlungRepository $zahlungRepository,
    ) {
    }

    /**
     * Calculate Vermögensabgrenzung data for HGA report.
     *
     * @return array<string, mixed>
     */
    public function calculateVermoegensabgrenzung(Weg $weg, int $year, string $bankkontoTyp = 'hausgeld'): array
    {
        // Get regular type for period end (e.g., hausgeld -> 30.12.YYYY)
        $kontostandPeriode = $this->kontostandRepository->findByWegYearAndType($weg, $year, $bankkontoTyp);

        // Get _stichtag type for actual bank statement date (e.g., hausgeld_stichtag -> 24.01.YYYY+1)
        $stichtagTyp = $bankkontoTyp . '_stichtag';
        $kontostandStichtag = $this->kontostandRepository->findByWegYearAndType($weg, $year, $stichtagTyp);

        // Need at least one record
        if (!$kontostandPeriode && !$kontostandStichtag) {
            return [
                'available' => false,
                'message' => 'Keine Kontostände für dieses Jahr erfasst. Bitte unter /abrechnung erfassen.',
            ];
        }

        // Use Stichtag record for payment period calculation (longer range)
        // Fall back to Periode record if Stichtag not available
        $kontostandForPayments = $kontostandStichtag ?? $kontostandPeriode;

        // Calculate payments in period
        $stichtagStart = $kontostandForPayments->getStichtagStart();
        $stichtagEnd = $kontostandForPayments->getStichtagEnd();

        $zahlungen = $this->zahlungRepository->findByWegAndDateRange(
            $weg,
            $stichtagStart,
            $stichtagEnd,
            $bankkontoTyp
        );

        // Group by Abrechnungsjahr
        $byAbrechnungsjahr = $this->groupByAbrechnungsjahr($zahlungen);

        // Calculate totals
        $periodeGesamt = $this->calculatePeriodeTotals($zahlungen);

        // Get balances from both records
        $saldoStart = (float) $kontostandForPayments->getSaldoStart();

        // Period end balance from regular type (e.g., hausgeld with 30.12 end date)
        $periodeEndDate = $kontostandPeriode ? $kontostandPeriode->getStichtagEnd() : null;
        $saldoPeriodeEnd = $kontostandPeriode ? (float) $kontostandPeriode->getSaldoEnd() : null;

        // Stichtag balance from _stichtag type (e.g., hausgeld_stichtag with actual bank date)
        $stichtagEndDate = $kontostandStichtag ? $kontostandStichtag->getStichtagEnd() : $stichtagEnd;
        $saldoStichtagEnd = $kontostandStichtag ? (float) $kontostandStichtag->getSaldoEnd() : (float) $kontostandForPayments->getSaldoEnd();

        // Calculate Abweichung based on Stichtag balance
        $rechnerisch = $saldoStart + $periodeGesamt['saldo'];
        $abweichung = $saldoStichtagEnd - $rechnerisch;

        return [
            'available' => true,
            'kontostand' => [
                'stichtag_start' => [
                    'datum' => $stichtagStart->format('d.m.Y'),
                    'saldo' => $saldoStart,
                ],
                'periode_end' => $kontostandPeriode ? [
                    'datum' => $periodeEndDate->format('d.m.Y'),
                    'saldo' => $saldoPeriodeEnd,
                ] : null,
                'stichtag_end' => [
                    'datum' => $stichtagEndDate->format('d.m.Y'),
                    'saldo' => $saldoStichtagEnd,
                ],
            ],
            'periode' => [
                'start' => $stichtagStart->format('Y-m-d'),
                'end' => $stichtagEnd->format('Y-m-d'),
                'gesamt' => $periodeGesamt,
                'nach_abrechnungsjahr' => $byAbrechnungsjahr,
            ],
            'abweichung' => [
                'rechnerisch' => $rechnerisch,
                'tatsaechlich' => $saldoEnd,
                'differenz' => $abweichung,
                'status' => abs($abweichung) < 0.01 ? 'ok' : 'unklar',
            ],
            'bemerkung' => $kontostand->getBemerkung(),
        ];
    }

    /**
     * Group Zahlungen by Abrechnungsjahr.
     *
     * @param array<int, \App\Entity\Zahlung> $zahlungen
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupByAbrechnungsjahr(array $zahlungen): array
    {
        $grouped = [];

        foreach ($zahlungen as $zahlung) {
            $abrechnungsjahr = $zahlung->getAbrechnungsjahrZuordnung() ?? $zahlung->getDatum()->format('Y');
            $year = (int) $abrechnungsjahr;

            if (!isset($grouped[$year])) {
                $grouped[$year] = [
                    'jahr' => $year,
                    'einnahmen' => 0.0,
                    'anzahl_einnahmen' => 0,
                    'ausgaben' => 0.0,
                    'anzahl_ausgaben' => 0,
                    'saldo' => 0.0,
                    'anzahl_gesamt' => 0,
                    'zahlungen' => [],
                    'einnahmen_by_category' => [],
                    'ausgaben_by_kostenkonto' => [],
                ];
            }

            $betrag = (float) $zahlung->getBetrag();

            if ($betrag > 0) {
                $grouped[$year]['einnahmen'] += $betrag;
                ++$grouped[$year]['anzahl_einnahmen'];

                // Group income by category
                $kategorie = $zahlung->getHauptkategorie()?->getName() ?? 'Sonstige Einnahme';
                if (!isset($grouped[$year]['einnahmen_by_category'][$kategorie])) {
                    $grouped[$year]['einnahmen_by_category'][$kategorie] = 0.0;
                }
                $grouped[$year]['einnahmen_by_category'][$kategorie] += $betrag;
            } else {
                $grouped[$year]['ausgaben'] += $betrag;
                ++$grouped[$year]['anzahl_ausgaben'];

                // Group expenses by Kostenkonto
                $kostenkonto = $zahlung->getKostenkonto();
                if ($kostenkonto) {
                    $key = $kostenkonto->getNummer() . ' ' . $kostenkonto->getBezeichnung();
                    if (!isset($grouped[$year]['ausgaben_by_kostenkonto'][$key])) {
                        $grouped[$year]['ausgaben_by_kostenkonto'][$key] = 0.0;
                    }
                    $grouped[$year]['ausgaben_by_kostenkonto'][$key] += $betrag;
                } else {
                    // No Kostenkonto assigned - check if it's a transfer (Umbuchung)
                    $hauptkategorie = $zahlung->getHauptkategorie();
                    $isUmbuchung = $hauptkategorie && 'Umbuchung' === $hauptkategorie->getName();
                    $key = $isUmbuchung ? 'Umbuchung Rücklage' : 'Ohne Kostenkonto';
                    if (!isset($grouped[$year]['ausgaben_by_kostenkonto'][$key])) {
                        $grouped[$year]['ausgaben_by_kostenkonto'][$key] = 0.0;
                    }
                    $grouped[$year]['ausgaben_by_kostenkonto'][$key] += $betrag;
                }
            }

            $grouped[$year]['saldo'] += $betrag;
            ++$grouped[$year]['anzahl_gesamt'];
            $grouped[$year]['zahlungen'][] = $zahlung;
        }

        // Sort by year
        ksort($grouped);

        // Sort each year's breakdown by amount (largest first)
        foreach ($grouped as &$yearData) {
            if (isset($yearData['einnahmen_by_category'])) {
                arsort($yearData['einnahmen_by_category']);
            }
            if (isset($yearData['ausgaben_by_kostenkonto'])) {
                asort($yearData['ausgaben_by_kostenkonto']); // Ascending because negative values
            }
        }

        return $grouped;
    }

    /**
     * Calculate totals for period.
     *
     * @param array<int, \App\Entity\Zahlung> $zahlungen
     *
     * @return array<string, mixed>
     */
    private function calculatePeriodeTotals(array $zahlungen): array
    {
        $einnahmen = 0.0;
        $anzahlEinnahmen = 0;
        $ausgaben = 0.0;
        $anzahlAusgaben = 0;

        foreach ($zahlungen as $zahlung) {
            $betrag = (float) $zahlung->getBetrag();

            if ($betrag > 0) {
                $einnahmen += $betrag;
                ++$anzahlEinnahmen;
            } else {
                $ausgaben += $betrag;
                ++$anzahlAusgaben;
            }
        }

        return [
            'einnahmen' => $einnahmen,
            'anzahl_einnahmen' => $anzahlEinnahmen,
            'ausgaben' => $ausgaben,
            'anzahl_ausgaben' => $anzahlAusgaben,
            'saldo' => $einnahmen + $ausgaben,
            'anzahl_gesamt' => \count($zahlungen),
        ];
    }
}
