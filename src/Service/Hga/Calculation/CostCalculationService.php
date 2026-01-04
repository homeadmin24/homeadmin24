<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\KategorisierungsTyp;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Entity\Zahlung;
use App\Repository\ZahlungRepository;
use App\Service\Hga\CalculationInterface;

/**
 * Cost calculation service for HGA.
 *
 * Handles categorization and calculation of different cost types
 * (umlagefähig, nicht umlagefähig, Rücklagen) with clean separation of concerns.
 */
class CostCalculationService
{
    public function __construct(
        private ZahlungRepository $zahlungRepository,
        private CalculationInterface $distributionService,
    ) {
    }

    /**
     * Calculate umlagefähige costs for a unit and year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateUmlagefaehigeCosts(WegEinheit $einheit, int $year): array
    {
        $weg = $einheit->getWeg();
        $mea = $this->extractMEAAsDecimal($einheit);

        $zahlungen = $this->zahlungRepository->getPaymentsByWegAndYear($weg, $year);

        return $this->calculateCostsByKategorisierungsTyp(
            $zahlungen,
            $mea,
            [KategorisierungsTyp::UMLAGEFAEHIG_HEIZUNG, KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE],
            $einheit,
            $weg
        );
    }

    /**
     * Calculate nicht umlagefähige costs for a unit and year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateNichtUmlagefaehigeCosts(WegEinheit $einheit, int $year): array
    {
        $weg = $einheit->getWeg();
        $mea = $this->extractMEAAsDecimal($einheit);

        $zahlungen = $this->zahlungRepository->getPaymentsByWegAndYear($weg, $year);

        return $this->calculateCostsByKategorisierungsTyp(
            $zahlungen,
            $mea,
            [KategorisierungsTyp::NICHT_UMLAGEFAEHIG],
            $einheit,
            $weg
        );
    }

    /**
     * Calculate Rücklagenzuführung for a unit and year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateRuecklagenzufuehrung(WegEinheit $einheit, int $year): array
    {
        $weg = $einheit->getWeg();
        $mea = $this->extractMEAAsDecimal($einheit);

        $zahlungen = $this->zahlungRepository->getPaymentsByWegAndYear($weg, $year);

        return $this->calculateCostsByKategorisierungsTyp(
            $zahlungen,
            $mea,
            [KategorisierungsTyp::RUECKLAGENZUFUEHRUNG],
            $einheit,
            $weg
        );
    }

    /**
     * Calculate total costs for all categories.
     *
     * @return array<string, mixed>
     */
    public function calculateTotalCosts(WegEinheit $einheit, int $year): array
    {
        $umlagefaehig = $this->calculateUmlagefaehigeCosts($einheit, $year);
        $nichtUmlagefaehig = $this->calculateNichtUmlagefaehigeCosts($einheit, $year);
        $ruecklagen = $this->calculateRuecklagenzufuehrung($einheit, $year);

        $totalUmlagefaehig = array_sum(array_column($umlagefaehig, 'anteil'));
        $totalNichtUmlagefaehig = array_sum(array_column($nichtUmlagefaehig, 'anteil'));
        $totalRuecklagen = array_sum(array_column($ruecklagen, 'anteil'));

        return [
            'umlagefaehig' => [
                'items' => $umlagefaehig,
                'total' => $totalUmlagefaehig,
            ],
            'nicht_umlagefaehig' => [
                'items' => $nichtUmlagefaehig,
                'total' => $totalNichtUmlagefaehig,
            ],
            'ruecklagen' => [
                'items' => $ruecklagen,
                'total' => $totalRuecklagen,
            ],
            // BGH V ZR 44/09: Rücklagen are not part of Gesamtkosten.
            'gesamtkosten' => $totalUmlagefaehig + $totalNichtUmlagefaehig,
        ];
    }

    /**
     * Calculate WEG totals directly from payments (no unit distribution).
     *
     * @return array<string, float>
     */
    public function calculateTotalCostsForWeg(Weg $weg, int $year): array
    {
        $zahlungen = $this->zahlungRepository->getPaymentsByWegAndYear($weg, $year);

        $totalUmlagefaehig = $this->calculateTotalsByKategorisierungsTyp(
            $zahlungen,
            [KategorisierungsTyp::UMLAGEFAEHIG_HEIZUNG, KategorisierungsTyp::UMLAGEFAEHIG_SONSTIGE]
        );
        $totalNichtUmlagefaehig = $this->calculateTotalsByKategorisierungsTyp(
            $zahlungen,
            [KategorisierungsTyp::NICHT_UMLAGEFAEHIG]
        );
        $totalRuecklagen = $this->calculateTotalsByKategorisierungsTyp(
            $zahlungen,
            [KategorisierungsTyp::RUECKLAGENZUFUEHRUNG]
        );

        return [
            'umlagefaehig' => $totalUmlagefaehig,
            'nicht_umlagefaehig' => $totalNichtUmlagefaehig,
            'ruecklagen' => $totalRuecklagen,
            'gesamtkosten' => $totalUmlagefaehig + $totalNichtUmlagefaehig,
        ];
    }

    /**
     * Calculate costs by KategorisierungsTyp.
     *
     * @param array<Zahlung>             $zahlungen
     * @param array<KategorisierungsTyp> $kategorisierungsTypen
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateCostsByKategorisierungsTyp(
        array $zahlungen,
        float $mea,
        array $kategorisierungsTypen,
        ?WegEinheit $einheit = null,
        ?Weg $weg = null,
    ): array {
        $result = [];
        $grouped = $this->groupZahlungenByKostenkonto($zahlungen, $kategorisierungsTypen, $einheit);

        foreach ($grouped as $item) {
            $verteilungsschluessel = $item['verteilungsschluessel'];

            try {
                // For 04* Festumlage, use the unit-specific amount directly
                if ('04*' === $verteilungsschluessel) {
                    $anteil = $item['unit_amount'] ?? 0.0;
                } else {
                    $anteil = $this->distributionService->calculateOwnerShare(
                        $item['total'],
                        $verteilungsschluessel,
                        $mea,
                        $einheit,
                        $weg
                    );
                }

                $result[] = [
                    'kostenkonto' => $item['kostenkonto'],
                    'beschreibung' => $item['beschreibung'],
                    'verteilungsschluessel' => $verteilungsschluessel,
                    'total' => $item['total'],
                    'anteil' => $anteil,
                    'count' => $item['count'],
                ];
            } catch (\Exception $e) {
                // Log error and continue with 0 amount
                $result[] = [
                    'kostenkonto' => $item['kostenkonto'],
                    'beschreibung' => $item['beschreibung'],
                    'verteilungsschluessel' => $verteilungsschluessel,
                    'total' => $item['total'],
                    'anteil' => 0.0,
                    'count' => $item['count'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Sum totals by KategorisierungsTyp without unit distribution.
     *
     * @param array<Zahlung>             $zahlungen
     * @param array<KategorisierungsTyp> $kategorisierungsTypen
     */
    private function calculateTotalsByKategorisierungsTyp(array $zahlungen, array $kategorisierungsTypen): float
    {
        $grouped = $this->groupZahlungenByKostenkonto($zahlungen, $kategorisierungsTypen);

        return array_sum(array_column($grouped, 'total'));
    }

    /**
     * Group Zahlungen by Kostenkonto and filter by KategorisierungsTyp.
     *
     * @param array<Zahlung>             $zahlungen
     * @param array<KategorisierungsTyp> $kategorisierungsTypen
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupZahlungenByKostenkonto(array $zahlungen, array $kategorisierungsTypen, ?WegEinheit $einheit = null): array
    {
        $grouped = [];

        foreach ($zahlungen as $zahlung) {
            $kostenkonto = $zahlung->getKostenkonto();

            if (!$kostenkonto || !\in_array($kostenkonto->getKategorisierungsTyp(), $kategorisierungsTypen, true)) {
                continue;
            }

            // Skip inactive kostenkonto accounts
            if (!$kostenkonto->isActive()) {
                continue;
            }

            // Skip income accounts - they are shown in payment overview instead
            if (KategorisierungsTyp::EINNAHMEN === $kostenkonto->getKategorisierungsTyp()) {
                continue;
            }

            // Skip externally calculated kostenkonto (01* Heiz-/Wasserkosten)
            // These are calculated by ExternalCostService instead
            $verteilungsschluessel = $kostenkonto->getUmlageschluessel()?->getSchluessel() ?? '05*';
            if (\in_array($verteilungsschluessel, ['01*'], true)) {
                continue;
            }

            $betrag = (float) $zahlung->getBetrag();

            $nummer = $kostenkonto->getNummer();

            if (!isset($grouped[$nummer])) {
                $grouped[$nummer] = [
                    'kostenkonto' => $nummer,
                    'beschreibung' => $kostenkonto->getBezeichnung(),
                    'verteilungsschluessel' => $verteilungsschluessel,
                    'total' => 0.0,
                    'unit_amount' => 0.0, // Track unit-specific amount for 04*
                    'count' => 0,
                ];
            }

            // For Festumlage (04*), track unit-specific amount separately
            $isUnitSpecificPayment = false;
            if ('04*' === $verteilungsschluessel && $einheit && $zahlung->getEigentuemer()) {
                if ($zahlung->getEigentuemer()->getId() === $einheit->getId()) {
                    $isUnitSpecificPayment = true;
                    // Track unit-specific amount
                    if ($betrag < 0) {
                        $grouped[$nummer]['unit_amount'] += abs($betrag);
                    } else {
                        $grouped[$nummer]['unit_amount'] -= $betrag;
                    }
                }
            }

            // Always include all payments in total for WEG overview
            // Handle negative amounts (costs) and positive amounts (refunds/corrections)
            if ($betrag < 0) {
                $grouped[$nummer]['total'] += abs($betrag);
            } else {
                // Subtract refunds/corrections from the total
                $grouped[$nummer]['total'] -= $betrag;
            }
            ++$grouped[$nummer]['count'];
        }

        // Sort by kostenkonto number
        ksort($grouped);

        return array_values($grouped);
    }

    /**
     * Extract MEA as decimal from WegEinheit.
     */
    private function extractMEAAsDecimal(WegEinheit $einheit): float
    {
        $meaString = $einheit->getMiteigentumsanteile();

        if (!$meaString) {
            throw new \InvalidArgumentException('No MEA value found for unit');
        }

        if (str_contains($meaString, '/')) {
            [$numerator, $denominator] = explode('/', $meaString, 2);

            if (0.0 === (float) $denominator) {
                throw new \InvalidArgumentException('Invalid MEA: division by zero');
            }

            return (float) $numerator / (float) $denominator;
        }

        return (float) $meaString / 1000; // Assuming 1000 as total if no fraction
    }
}
