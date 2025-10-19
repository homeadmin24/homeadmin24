<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\WegEinheit;
use App\Entity\Zahlung;
use App\Repository\ZahlungRepository;
use App\Service\Hga\CalculationInterface;
use App\Service\Hga\ConfigurationInterface;

/**
 * Tax calculation service for §35a EStG deductions.
 *
 * Calculates tax-deductible amounts based on labor costs
 * from invoices associated with payments.
 */
class TaxCalculationService
{
    public function __construct(
        private ZahlungRepository $zahlungRepository,
        private ConfigurationInterface $configurationService,
        private CalculationInterface $distributionService,
    ) {
    }

    /**
     * Calculate tax-deductible amounts for a unit and year.
     *
     * @return array{
     *   items: array<array{
     *     kostenkonto: string,
     *     beschreibung: string,
     *     gesamtkosten: float,
     *     anrechenbar: float,
     *     anteil_kosten: float,
     *     anteil_anrechenbar: float
     *   }>,
     *   total_anrechenbar: float
     * }
     */
    public function calculateTaxDeductible(WegEinheit $einheit, int $year): array
    {
        $weg = $einheit->getWeg();
        $mea = $this->extractMEAAsDecimal($einheit);

        // Get tax-deductible account numbers from configuration
        $taxDeductibleAccounts = $this->configurationService->getTaxDeductibleAccounts();

        // Get all payments for the year
        $zahlungen = $this->zahlungRepository->getPaymentsByWegAndYear($weg, $year);

        $items = [];
        $totalAnrechenbar = 0.0;

        // Group by kostenkonto and calculate
        $grouped = $this->groupTaxDeductiblePayments($zahlungen, $taxDeductibleAccounts);

        foreach ($grouped as $group) {
            $laborCostPercentage = $this->calculateLaborCostPercentage($group['zahlungen']);
            $anrechenbar = $group['total'] * $laborCostPercentage;

            $anteil = $this->distributionService->calculateOwnerShare(
                $group['total'],
                $group['verteilungsschluessel'],
                $mea,
                $einheit,
                $weg
            );

            $anteilAnrechenbar = $this->distributionService->calculateOwnerShare(
                $anrechenbar,
                $group['verteilungsschluessel'],
                $mea,
                $einheit,
                $weg
            );

            $items[] = [
                'kostenkonto' => $group['kostenkonto'],
                'beschreibung' => $group['beschreibung'],
                'verteilungsschluessel' => $group['verteilungsschluessel'],
                'gesamtkosten' => $group['total'],
                'anrechenbar' => $anrechenbar,
                'anteil_kosten' => $anteil,
                'anteil_anrechenbar' => $anteilAnrechenbar,
            ];

            $totalAnrechenbar += $anteilAnrechenbar;
        }

        return [
            'items' => $items,
            'total_anrechenbar' => $totalAnrechenbar,
        ];
    }

    /**
     * Group tax-deductible payments by kostenkonto.
     *
     * @param array<Zahlung> $zahlungen
     * @param array<string>  $taxDeductibleAccounts
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupTaxDeductiblePayments(array $zahlungen, array $taxDeductibleAccounts): array
    {
        $grouped = [];

        foreach ($zahlungen as $zahlung) {
            $kostenkonto = $zahlung->getKostenkonto();

            if (!$kostenkonto || !\in_array($kostenkonto->getNummer(), $taxDeductibleAccounts, true)) {
                continue;
            }

            $nummer = $kostenkonto->getNummer();

            if (!isset($grouped[$nummer])) {
                $grouped[$nummer] = [
                    'kostenkonto' => $nummer,
                    'beschreibung' => $kostenkonto->getBezeichnung(),
                    'verteilungsschluessel' => $kostenkonto->getUmlageschluessel()?->getSchluessel() ?? '05*',
                    'total' => 0.0,
                    'zahlungen' => [],
                ];
            }

            $grouped[$nummer]['total'] += abs((float) $zahlung->getBetrag());
            $grouped[$nummer]['zahlungen'][] = $zahlung;
        }

        return array_values($grouped);
    }

    /**
     * Calculate labor cost percentage for a set of payments.
     *
     * @param array<Zahlung> $zahlungen
     *
     * @return float Percentage of labor costs (0.0 to 1.0)
     */
    private function calculateLaborCostPercentage(array $zahlungen): float
    {
        $totalAmount = 0.0;
        $totalLaborCosts = 0.0;

        foreach ($zahlungen as $zahlung) {
            $totalAmount += abs((float) $zahlung->getBetrag());

            // Get associated invoice
            $rechnung = $zahlung->getRechnung();

            if ($rechnung) {
                $arbeitsFahrtkosten = $rechnung->getArbeitsFahrtkosten();

                if (null !== $arbeitsFahrtkosten && (float) $arbeitsFahrtkosten > 0) {
                    // Calculate proportional labor costs for this payment
                    $rechnungTotal = (float) $rechnung->getBetragMitSteuern();

                    if ($rechnungTotal > 0) {
                        $laborPercentage = (float) $arbeitsFahrtkosten / $rechnungTotal;
                        $totalLaborCosts += abs((float) $zahlung->getBetrag()) * $laborPercentage;
                    }
                }
            }
        }

        if (0.0 === $totalAmount) {
            return 0.0;
        }

        return $totalLaborCosts / $totalAmount;
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

        return (float) $meaString / 1000;
    }
}
