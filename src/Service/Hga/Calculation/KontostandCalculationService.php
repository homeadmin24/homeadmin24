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
     * Returns two separate periods:
     * - periode_abrechnung: 01.01 - 30.12 (BGH V ZR 271/12 compliant)
     * - periode_abgrenzung: 31.12 - Stichtag (payments after period end)
     *
     * @return array<string, mixed>
     */
    public function calculateVermoegensabgrenzung(Weg $weg, int $year, string $bankkontoTyp = 'hausgeld'): array
    {
        $kontostand = $this->kontostandRepository->findByWegYearAndType($weg, $year, $bankkontoTyp);

        if (!$kontostand) {
            return [
                'available' => false,
                'message' => 'Keine Kontostände für dieses Jahr erfasst. Bitte unter /abrechnung erfassen.',
            ];
        }

        // All dates and balances from single merged row
        $stichtagStart = $kontostand->getStichtagStart();
        $stichtagEnd = $kontostand->getStichtagEnd();

        // Period end date (BGH V ZR 271/12, e.g. 30.12.YYYY)
        $periodeEndDateInterface = $kontostand->getStichtagEndPeriode();
        $periodeEndDate = $periodeEndDateInterface instanceof \DateTime
            ? $periodeEndDateInterface
            : new \DateTime($periodeEndDateInterface?->format('Y-m-d') ?? $year . '-12-30');

        // Balances
        $saldoStart = (float) $kontostand->getSaldoStart();
        $saldoPeriodeEnd = null !== $kontostand->getSaldoEndPeriode() ? (float) $kontostand->getSaldoEndPeriode() : null;
        $stichtagEndDate = $stichtagEnd;
        $saldoStichtagEnd = (float) $kontostand->getSaldoEnd();

        // === PERIODE ABRECHNUNG (01.01 - 30.12) - BGH V ZR 271/12 ===
        $zahlungenAbrechnung = $this->zahlungRepository->findByWegAndDateRange(
            $weg,
            $stichtagStart,
            $periodeEndDate,
            $bankkontoTyp
        );
        $byAbrechnungsjahrAbrechnung = $this->groupByAbrechnungsjahr($zahlungenAbrechnung);
        $periodeAbrechnungGesamt = $this->calculatePeriodeTotals($zahlungenAbrechnung);

        // === PERIODE ABGRENZUNG (31.12 - Stichtag) - Nach Periodenende ===
        $abgrenzungStart = (clone $periodeEndDate)->modify('+1 day');
        $zahlungenAbgrenzung = [];
        $byAbrechnungsjahrAbgrenzung = [];
        $periodeAbgrenzungGesamt = [
            'einnahmen' => 0.0,
            'anzahl_einnahmen' => 0,
            'ausgaben' => 0.0,
            'anzahl_ausgaben' => 0,
            'saldo' => 0.0,
            'anzahl_gesamt' => 0,
        ];

        // Only calculate Abgrenzung if Stichtag is after Periodenende
        if ($stichtagEnd > $periodeEndDate) {
            $zahlungenAbgrenzung = $this->zahlungRepository->findByWegAndDateRange(
                $weg,
                $abgrenzungStart,
                $stichtagEnd,
                $bankkontoTyp
            );
            $byAbrechnungsjahrAbgrenzung = $this->groupByAbrechnungsjahr($zahlungenAbgrenzung);
            $periodeAbgrenzungGesamt = $this->calculatePeriodeTotals($zahlungenAbgrenzung);
        }

        // Calculate Abweichung based on full period (for bank reconciliation)
        $allZahlungen = array_merge($zahlungenAbrechnung, $zahlungenAbgrenzung);
        $periodeGesamtAll = $this->calculatePeriodeTotals($allZahlungen);
        $rechnerisch = $saldoStart + $periodeGesamtAll['saldo'];
        $abweichung = $saldoStichtagEnd - $rechnerisch;

        return [
            'available' => true,
            'kontostand' => [
                'stichtag_start' => [
                    'datum' => $stichtagStart->format('d.m.Y'),
                    'saldo' => $saldoStart,
                ],
                'periode_end' => $kontostand->getStichtagEndPeriode() ? [
                    'datum' => $periodeEndDate->format('d.m.Y'),
                    'saldo' => $saldoPeriodeEnd,
                ] : null,
                'stichtag_end' => [
                    'datum' => $stichtagEndDate->format('d.m.Y'),
                    'saldo' => $saldoStichtagEnd,
                ],
            ],
            // Abrechnungsperiode (BGH V ZR 271/12): 01.01 - 30.12
            'periode_abrechnung' => [
                'start' => $stichtagStart->format('Y-m-d'),
                'end' => $periodeEndDate->format('Y-m-d'),
                'start_formatted' => $stichtagStart->format('d.m.Y'),
                'end_formatted' => $periodeEndDate->format('d.m.Y'),
                'gesamt' => $periodeAbrechnungGesamt,
                'nach_abrechnungsjahr' => $byAbrechnungsjahrAbrechnung,
            ],
            // Abgrenzung: Zahlungen nach Periodenende bis Stichtag
            'periode_abgrenzung' => [
                'start' => $abgrenzungStart->format('Y-m-d'),
                'end' => $stichtagEnd->format('Y-m-d'),
                'start_formatted' => $abgrenzungStart->format('d.m.Y'),
                'end_formatted' => $stichtagEnd->format('d.m.Y'),
                'gesamt' => $periodeAbgrenzungGesamt,
                'nach_abrechnungsjahr' => $byAbrechnungsjahrAbgrenzung,
                'hinweis' => 'Diese Zahlungen wurden nach dem Abrechnungszeitraum geleistet und erscheinen in der Abrechnung ' . ($year + 1) . '.',
            ],
            // Legacy: Full period for backwards compatibility
            'periode' => [
                'start' => $stichtagStart->format('Y-m-d'),
                'end' => $stichtagEnd->format('Y-m-d'),
                'gesamt' => $periodeGesamtAll,
                'nach_abrechnungsjahr' => $this->groupByAbrechnungsjahr($allZahlungen),
            ],
            'abweichung' => [
                'rechnerisch' => $rechnerisch,
                'tatsaechlich' => $saldoStichtagEnd,
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
