<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\BankkontoTyp;
use App\Entity\Weg;
use App\Entity\WegKontostand;
use App\Entity\Zahlung;
use App\Repository\WegKontostandRepository;
use App\Repository\ZahlungRepository;

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
    public function calculateVermoegensabgrenzung(Weg $weg, int $year, BankkontoTyp $bankkontoTyp = BankkontoTyp::HAUSGELD): array
    {
        $kontostand = $this->kontostandRepository->findByWegYearAndType($weg, $year, $bankkontoTyp);

        if (!$kontostand) {
            return [
                'available' => false,
                'message' => 'Keine Kontostände für dieses Jahr erfasst. Bitte unter /abrechnung erfassen.',
            ];
        }

        $periodeEndDate = $this->resolvePeriodeEndDate($kontostand, $year);
        $saldoStart = (float) $kontostand->getSaldoStart();
        $saldoStichtagEnd = (float) $kontostand->getSaldoEnd();

        $periodeAbrechnung = $this->calculatePeriodeAbrechnung($weg, $kontostand, $periodeEndDate, $bankkontoTyp);
        $periodeAbgrenzung = $this->calculatePeriodeAbgrenzung($weg, $kontostand, $periodeEndDate, $year, $bankkontoTyp);

        $allZahlungen = array_merge($periodeAbrechnung['zahlungen'], $periodeAbgrenzung['zahlungen']);
        $periodeGesamtAll = $this->calculatePeriodeTotals($allZahlungen);
        $rechnerisch = $saldoStart + $periodeGesamtAll['saldo'];

        return [
            'available' => true,
            'kontostand' => $this->buildKontostandSection($kontostand, $periodeEndDate, $saldoStart, $saldoStichtagEnd),
            'periode_abrechnung' => $periodeAbrechnung['result'],
            'periode_abgrenzung' => $periodeAbgrenzung['result'],
            'abweichung' => [
                'rechnerisch' => $rechnerisch,
                'tatsaechlich' => $saldoStichtagEnd,
                'differenz' => $saldoStichtagEnd - $rechnerisch,
                'status' => abs($saldoStichtagEnd - $rechnerisch) < 0.01 ? 'ok' : 'unklar',
            ],
            'bemerkung' => $kontostand->getBemerkung(),
        ];
    }

    private function resolvePeriodeEndDate(WegKontostand $kontostand, int $year): \DateTime
    {
        $periodeEndDateInterface = $kontostand->getStichtagEndPeriode();

        return $periodeEndDateInterface instanceof \DateTime
            ? $periodeEndDateInterface
            : new \DateTime($periodeEndDateInterface?->format('Y-m-d') ?? $year . '-12-30');
    }

    /**
     * @return array{zahlungen: Zahlung[], result: array<string, mixed>}
     */
    private function calculatePeriodeAbrechnung(Weg $weg, WegKontostand $kontostand, \DateTime $periodeEndDate, BankkontoTyp $bankkontoTyp): array
    {
        $stichtagStart = $kontostand->getStichtagStart();
        $zahlungen = $this->zahlungRepository->findByWegAndDateRange($weg, $stichtagStart, $periodeEndDate, $bankkontoTyp->value);

        return [
            'zahlungen' => $zahlungen,
            'result' => [
                'start' => $stichtagStart->format('Y-m-d'),
                'end' => $periodeEndDate->format('Y-m-d'),
                'start_formatted' => $stichtagStart->format('d.m.Y'),
                'end_formatted' => $periodeEndDate->format('d.m.Y'),
                'gesamt' => $this->calculatePeriodeTotals($zahlungen),
                'nach_abrechnungsjahr' => $this->groupByAbrechnungsjahr($zahlungen),
            ],
        ];
    }

    /**
     * @return array{zahlungen: Zahlung[], result: array<string, mixed>}
     */
    private function calculatePeriodeAbgrenzung(Weg $weg, WegKontostand $kontostand, \DateTime $periodeEndDate, int $year, BankkontoTyp $bankkontoTyp): array
    {
        $stichtagEnd = $kontostand->getStichtagEnd();
        $abgrenzungStart = (clone $periodeEndDate)->modify('+1 day');

        if ($stichtagEnd <= $periodeEndDate) {
            return [
                'zahlungen' => [],
                'result' => [
                    'start' => $abgrenzungStart->format('Y-m-d'),
                    'end' => $stichtagEnd->format('Y-m-d'),
                    'start_formatted' => $abgrenzungStart->format('d.m.Y'),
                    'end_formatted' => $stichtagEnd->format('d.m.Y'),
                    'gesamt' => $this->calculatePeriodeTotals([]),
                    'nach_abrechnungsjahr' => [],
                    'hinweis' => 'Diese Zahlungen wurden nach dem Abrechnungszeitraum geleistet und erscheinen in der Abrechnung ' . ($year + 1) . '.',
                ],
            ];
        }

        $zahlungen = $this->zahlungRepository->findByWegAndDateRange($weg, $abgrenzungStart, $stichtagEnd, $bankkontoTyp->value);

        return [
            'zahlungen' => $zahlungen,
            'result' => [
                'start' => $abgrenzungStart->format('Y-m-d'),
                'end' => $stichtagEnd->format('Y-m-d'),
                'start_formatted' => $abgrenzungStart->format('d.m.Y'),
                'end_formatted' => $stichtagEnd->format('d.m.Y'),
                'gesamt' => $this->calculatePeriodeTotals($zahlungen),
                'nach_abrechnungsjahr' => $this->groupByAbrechnungsjahr($zahlungen),
                'hinweis' => 'Diese Zahlungen wurden nach dem Abrechnungszeitraum geleistet und erscheinen in der Abrechnung ' . ($year + 1) . '.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKontostandSection(WegKontostand $kontostand, \DateTime $periodeEndDate, float $saldoStart, float $saldoStichtagEnd): array
    {
        $saldoEndPeriode = null !== $kontostand->getSaldoEndPeriode() ? (float) $kontostand->getSaldoEndPeriode() : null;

        return [
            'stichtag_start' => [
                'datum' => $kontostand->getStichtagStart()->format('d.m.Y'),
                'saldo' => $saldoStart,
            ],
            'periode_end' => $kontostand->getStichtagEndPeriode() ? [
                'datum' => $periodeEndDate->format('d.m.Y'),
                'saldo' => $saldoEndPeriode,
            ] : null,
            'stichtag_end' => [
                'datum' => $kontostand->getStichtagEnd()->format('d.m.Y'),
                'saldo' => $saldoStichtagEnd,
            ],
        ];
    }

    /**
     * @param Zahlung[] $zahlungen
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

                $kategorie = $zahlung->getHauptkategorie()?->getName() ?? 'Sonstige Einnahme';
                $grouped[$year]['einnahmen_by_category'][$kategorie] = ($grouped[$year]['einnahmen_by_category'][$kategorie] ?? 0.0) + $betrag;
            } else {
                $grouped[$year]['ausgaben'] += $betrag;
                ++$grouped[$year]['anzahl_ausgaben'];

                $key = $this->resolveAusgabenKey($zahlung);
                $grouped[$year]['ausgaben_by_kostenkonto'][$key] = ($grouped[$year]['ausgaben_by_kostenkonto'][$key] ?? 0.0) + $betrag;
            }

            $grouped[$year]['saldo'] += $betrag;
            ++$grouped[$year]['anzahl_gesamt'];
            $grouped[$year]['zahlungen'][] = $zahlung;
        }

        ksort($grouped);

        foreach ($grouped as &$yearData) {
            arsort($yearData['einnahmen_by_category']);
            asort($yearData['ausgaben_by_kostenkonto']);
        }

        return $grouped;
    }

    private function resolveAusgabenKey(Zahlung $zahlung): string
    {
        $kostenkonto = $zahlung->getKostenkonto();

        if ($kostenkonto) {
            return $kostenkonto->getNummer() . ' ' . $kostenkonto->getBezeichnung();
        }

        $hauptkategorie = $zahlung->getHauptkategorie();
        $isUmbuchung = $hauptkategorie && 'Umbuchung' === $hauptkategorie->getName();

        return $isUmbuchung ? 'Umbuchung Rücklage' : 'Ohne Kostenkonto';
    }

    /**
     * @param Zahlung[] $zahlungen
     *
     * @return array<string, float|int>
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
