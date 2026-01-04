<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Service\Hga\Calculation\BalanceCalculationService;
use App\Service\Hga\Calculation\CostCalculationService;
use App\Service\Hga\Calculation\DistributionService;
use App\Service\Hga\Calculation\ExternalCostService;
use App\Service\Hga\Calculation\PaymentCalculationService;
use App\Service\Hga\Calculation\TaxCalculationService;
use Psr\Log\LoggerInterface;

/**
 * Main HGA service implementation.
 *
 * Orchestrates all HGA calculations and provides the primary API
 * for Hausgeldabrechnung operations.
 */
class HgaService implements HgaServiceInterface
{
    public function __construct(
        private CostCalculationService $costCalculationService,
        private PaymentCalculationService $paymentCalculationService,
        private TaxCalculationService $taxCalculationService,
        private ExternalCostService $externalCostService,
        private BalanceCalculationService $balanceCalculationService,
        private DistributionService $distributionService,
        private ConfigurationInterface $configurationService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generateReportData(WegEinheit $einheit, int $year): array
    {
        $errors = $this->validateCalculationInputs($einheit, $year);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid inputs: ' . implode(', ', $errors));
        }

        try {
            // Get all calculation data
            $costs = $this->calculateOwnerCosts($einheit, $year);
            $payments = $this->calculatePaymentBalance($einheit, $year);
            $taxDeductible = $this->calculateTaxDeductible($einheit, $year);
            $externalCosts = $this->externalCostService->getAllExternalCosts($einheit, $year);
            $balanceData = $this->balanceCalculationService->getBalanceData($einheit->getWeg(), $year);
            $wirtschaftsplanData = $this->configurationService->getWirtschaftsplanData();

            // Get WEG totals
            $wegCostTotals = $this->calculateTotalCosts($einheit->getWeg(), $year);

            // Calculate final totals for display (BGH V ZR 44/09 compliant)
            $calculatedTotals = $this->calculateFinalTotals($costs, $externalCosts, $payments);

            // Generate Umlageschlüssel data
            $umlageschluessel = $this->generateUmlageschluesselData($einheit, $year);

            // Build complete report data structure
            return [
                'einheit' => [
                    'nummer' => $einheit->getNummer(),
                    'beschreibung' => $einheit->getBezeichnung(),
                    'eigentuemer' => $einheit->getMiteigentuemer(),
                    'mea' => $einheit->getMiteigentumsanteile(),
                    'hebeanlage' => $einheit->getHebeanlage(),
                    'custom_distribution_02' => $this->distributionService->getDistributionShare($einheit, '02*'),
                    'address' => $einheit->getAdresse(), // Single address field
                ],
                'weg' => [
                    'name' => $einheit->getWeg()->getBezeichnung(),
                    'adresse' => $einheit->getWeg()->getAdresse(),
                ],
                'year' => $year,
                'costs' => $costs,
                'payments' => $payments,
                'tax_deductible' => $taxDeductible,
                'external_costs' => $externalCosts,
                'balance' => $balanceData,
                'wirtschaftsplan' => $wirtschaftsplanData,
                'umlageschluessel' => $umlageschluessel,
                'weg_totals' => [
                    'gesamtkosten' => $wegCostTotals['gesamtkosten'],
                    'soll' => $payments['weg_totals']['soll'] ?? 0.0,
                    'ist' => $payments['weg_totals']['ist'] ?? 0.0,
                    'cost_breakdown' => $wegCostTotals,
                ],
                'calculated_totals' => $calculatedTotals,
                'calculation_date' => new \DateTime(),
                'configuration' => [
                    'section_headers' => $this->configurationService->getSectionHeaders(),
                    'standard_texts' => $this->configurationService->getStandardTexts(),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate report data', [
                'einheit' => $einheit->getId(),
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to generate report data: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function calculateTotalCosts(Weg $weg, int $year): array
    {
        $totals = $this->costCalculationService->calculateTotalCostsForWeg($weg, $year);

        // External costs are handled in the TXT generator display layer
        $externalCostTotals = $this->externalCostService->getTotalExternalCostsForWeg($weg, $year);

        // BGH V ZR 44/09: Rücklagen are NOT part of Gesamtkosten
        // They are income to the reserve account, not expenses
        return [
            'umlagefaehig' => $totals['umlagefaehig'],
            'nicht_umlagefaehig' => $totals['nicht_umlagefaehig'],
            'ruecklagen' => $totals['ruecklagen'],
            'external_costs' => $externalCostTotals,
            'gesamtkosten' => $totals['gesamtkosten'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function calculateOwnerCosts(WegEinheit $einheit, int $year): array
    {
        $costs = $this->costCalculationService->calculateTotalCosts($einheit, $year);

        // External costs are handled in the TXT generator display layer
        // The HGA service should only return the database payment totals

        return $costs;
    }

    /**
     * {@inheritdoc}
     */
    public function calculatePaymentBalance(WegEinheit $einheit, int $year): array
    {
        $balance = $this->paymentCalculationService->calculatePaymentBalance($einheit, $year);
        $paymentDetails = $this->paymentCalculationService->getPaymentDetails($einheit, $year);

        // Get WEG totals for context
        $wegSollTotal = $this->paymentCalculationService->calculateTotalAdvancePaymentsForWeg($einheit, $year);
        $wegIstTotal = $this->paymentCalculationService->calculateTotalActualPaymentsForWeg($einheit, $year);

        // Get WEG total Rücklagen
        $wegRuecklagenTotal = $this->calculateTotalRuecklagenForWeg($einheit->getWeg(), $year);

        // Get monthly amounts for display
        // For ZAHLUNGSÜBERSICHT: monthly_weg_ist should show planned SOLL (1.500€), not actual payments
        $monthlyWegSoll = $this->paymentCalculationService->getMonthlyAdvancePaymentForWeg($einheit, $year);
        $monthlyUnitSoll = $this->configurationService->getMonthlyAmount($einheit, $year);

        // Group unit payments by month and type
        $monthlyUnitPayments = [];
        foreach ($paymentDetails as $payment) {
            $month = (int) $payment['datum']->format('n');
            if (!isset($monthlyUnitPayments[$month])) {
                $monthlyUnitPayments[$month] = ['wohngeld' => 0.0, 'other' => []];
            }

            // Use category to identify payment types
            $kategorie = $payment['kategorie'] ?? null;
            if ($kategorie === 'Hausgeld-Zahlung') {
                $monthlyUnitPayments[$month]['wohngeld'] += $payment['betrag'];
            } else {
                $monthlyUnitPayments[$month]['other'][] = $payment;
            }
        }

        // Calculate WEG totals for other payments by category
        $wegCategoryTotals = $this->getWegOtherPayments($einheit->getWeg(), $year);

        // For ZAHLUNGSÜBERSICHT display: use monthly SOLL values for consistent display
        // Create array with SOLL value for all 12 months
        $monthlyWegIstDisplay = array_fill(1, 12, $monthlyWegSoll);

        return array_merge($balance, [
            'payment_details' => $paymentDetails,
            'monthly_unit_payments' => $monthlyUnitPayments,
            'weg_category_totals' => $wegCategoryTotals,
            'weg_totals' => [
                'soll' => $wegSollTotal,
                'ist' => $wegIstTotal,
                'differenz' => $wegIstTotal - $wegSollTotal,
                'ruecklagen' => $wegRuecklagenTotal,
                'monthly_weg_soll' => $monthlyWegSoll,
                'monthly_unit_soll' => $monthlyUnitSoll,
                'monthly_weg_ist' => $monthlyWegIstDisplay,
            ],
        ]);
    }

    /**
     * Get WEG totals for other payments (Nachzahlungen, Sonderumlagen).
     * Returns totals grouped by category.
     *
     * @return array<string, float> Kategorie => WEG total
     */
    private function getWegOtherPayments(Weg $weg, int $year): array
    {
        $units = $weg->getEinheiten();

        // Calculate totals by category
        $categoryTotals = [];
        foreach ($units as $unit) {
            $payments = $this->paymentCalculationService->getPaymentDetails($unit, $year);

            foreach ($payments as $payment) {
                $kategorie = $payment['kategorie'] ?? 'Unbekannt';
                // Only process non-Hausgeld payments (Nachzahlungen, Sonderumlagen, etc.)
                if ($kategorie !== 'Hausgeld-Zahlung') {
                    if (!isset($categoryTotals[$kategorie])) {
                        $categoryTotals[$kategorie] = 0.0;
                    }
                    $categoryTotals[$kategorie] += $payment['betrag'];
                }
            }
        }

        return $categoryTotals;
    }

    /**
     * Calculate total Rücklagenzuführung for all units in the WEG.
     */
    private function calculateTotalRuecklagenForWeg(Weg $weg, int $year): float
    {
        $units = $weg->getEinheiten();

        $total = 0.0;
        foreach ($units as $unit) {
            $ruecklagen = $this->costCalculationService->calculateRuecklagenzufuehrung($unit, $year);
            foreach ($ruecklagen as $item) {
                $total += $item['anteil'];
            }
        }

        return $total;
    }

    /**
     * {@inheritdoc}
     */
    public function calculateTaxDeductible(WegEinheit $einheit, int $year): array
    {
        return $this->taxCalculationService->calculateTaxDeductible($einheit, $year);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCalculationInputs(WegEinheit $einheit, int $year): array
    {
        $errors = [];

        // Validate unit has required data
        if (!$einheit->getMiteigentumsanteile()) {
            $errors[] = 'Unit missing MEA value';
        }

        if (!$einheit->getWeg()) {
            $errors[] = 'Unit not associated with a WEG';
        }

        // Validate year
        $currentYear = (int) date('Y');
        if ($year < 2000 || $year > $currentYear + 1) {
            $errors[] = 'Invalid year: must be between 2000 and ' . ($currentYear + 1);
        }

        // Validate external cost data exists
        $externalCostErrors = $this->externalCostService->validateExternalCostData($einheit->getWeg(), $year);
        $errors = array_merge($errors, $externalCostErrors);

        return $errors;
    }

    /**
     * Calculate final totals for ABRECHNUNGSÜBERSICHT and display.
     *
     * This centralizes the calculation logic that was previously duplicated
     * in TxtReportGenerator and pdf_report.html.twig.
     *
     * BGH V ZR 44/09 compliant: Rücklagen are NOT included in Gesamtkosten.
     *
     * @param array<string, mixed> $costs
     * @param array<string, mixed> $externalCosts
     * @param array<string, mixed> $payments
     *
     * @return array<string, mixed>
     */
    private function calculateFinalTotals(array $costs, array $externalCosts, array $payments): array
    {
        // Calculate WEG totals
        $heizungWasserWegTotal = ($externalCosts['heating']['total'] ?? 0.0) +
                                  ($externalCosts['water']['total'] ?? 0.0);

        $umlagefaehigWegTotal = $heizungWasserWegTotal;
        foreach ($costs['umlagefaehig']['items'] ?? [] as $item) {
            $umlagefaehigWegTotal += $item['total'];
        }

        $nichtUmlagefaehigWegTotal = 0.0;
        foreach ($costs['nicht_umlagefaehig']['items'] ?? [] as $item) {
            $nichtUmlagefaehigWegTotal += $item['total'];
        }

        // BGH V ZR 44/09: Rücklagen NOT included in Gesamtkosten
        $wegGesamtkosten = $umlagefaehigWegTotal + $nichtUmlagefaehigWegTotal;

        // Calculate Unit totals
        $heizungWasserUnitTotal = ($externalCosts['heating']['unit_share'] ?? 0.0) +
                                   ($externalCosts['water']['unit_share'] ?? 0.0);

        $umlagefaehigUnitTotal = $heizungWasserUnitTotal;
        foreach ($costs['umlagefaehig']['items'] ?? [] as $item) {
            $umlagefaehigUnitTotal += $item['anteil'];
        }

        $nichtUmlagefaehigUnitTotal = 0.0;
        foreach ($costs['nicht_umlagefaehig']['items'] ?? [] as $item) {
            $nichtUmlagefaehigUnitTotal += $item['anteil'];
        }

        // BGH V ZR 44/09: Rücklagen NOT included in Gesamtkosten
        $unitGesamtkosten = $umlagefaehigUnitTotal + $nichtUmlagefaehigUnitTotal;

        // Calculate Saldo (final balance)
        $unitSoll = $payments['soll'] ?? 0.0;
        $unitIst = $payments['ist'] ?? 0.0;
        $abrechnungsspitze = $unitGesamtkosten - $unitSoll;
        $zahlungsdifferenz = $unitIst - $unitSoll;
        $saldo = $unitGesamtkosten - $unitIst;

        return [
            'weg' => [
                'umlagefaehig' => $umlagefaehigWegTotal,
                'nicht_umlagefaehig' => $nichtUmlagefaehigWegTotal,
                'gesamtkosten' => $wegGesamtkosten,
                'heizung_wasser' => $heizungWasserWegTotal,
            ],
            'unit' => [
                'umlagefaehig' => $umlagefaehigUnitTotal,
                'nicht_umlagefaehig' => $nichtUmlagefaehigUnitTotal,
                'gesamtkosten' => $unitGesamtkosten,
                'heizung_wasser' => $heizungWasserUnitTotal,
            ],
            'balance' => [
                'abrechnungsspitze' => $abrechnungsspitze,
                'zahlungsdifferenz' => $zahlungsdifferenz,
                'saldo' => $saldo,
                'is_guthaben' => $saldo < 0,
                'saldo_abs' => abs($saldo),
            ],
        ];
    }

    /**
     * Generate Umlageschlüssel data for report.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateUmlageschluesselData(WegEinheit $einheit, int $year): array
    {
        // Extract MEA as decimal
        $meaString = $einheit->getMiteigentumsanteile();
        $mea = 0.0;
        if ($meaString) {
            if (str_contains($meaString, '/')) {
                [$numerator, $denominator] = explode('/', $meaString, 2);
                if ((float) $denominator > 0) {
                    $mea = (float) $numerator / (float) $denominator;
                }
            } else {
                $mea = (float) $meaString / 1000;
            }
        }

        // Unit specific values
        $unitCount = 4.0; // Fixed for this WEG
        $hebeanlageShare = $einheit->getHebeanlage() ? 1.0 : 0.0;

        // Get 02* custom distribution share
        $customDistribution02 = $this->distributionService->getDistributionShare($einheit, '02*');
        $customShare02 = 0.0;
        if ($customDistribution02 && preg_match('/^(\d+)\/(\d+)$/', $customDistribution02, $matches)) {
            $numerator = (float) $matches[1];
            $denominator = (float) $matches[2];
            if ($denominator > 0) {
                $customShare02 = $numerator / $denominator;
            }
        }

        return [
            [
                'nummer' => '01*',
                'bezeichnung' => 'ext. berechn. Heiz-/Wasserkosten',
                'umlage_typ' => '€ Festbetrag',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => 'Beträge siehe Ergebnisliste',
                'anteil' => null,
            ],
            [
                'nummer' => '03*',
                'bezeichnung' => 'Anzahl Einheit',
                'umlage_typ' => 'Einheiten-anteilig',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => $unitCount,
                'anteil' => 1.0,
            ],
            [
                'nummer' => '04*',
                'bezeichnung' => 'Festumlage',
                'umlage_typ' => '€ Festbetrag',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => 'Beträge siehe Ergebnisliste',
                'anteil' => null,
            ],
            [
                'nummer' => '05*',
                'bezeichnung' => 'Miteigentumsanteil',
                'umlage_typ' => 'Anzahl anteilig',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => '1.000,000',
                'anteil' => $mea * 1000,
            ],
            [
                'nummer' => '06*',
                'bezeichnung' => 'Hebeanlage',
                'umlage_typ' => 'Spezial',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => '6,00',
                'anteil' => $hebeanlageShare,
            ],
            [
                'nummer' => '02*',
                'bezeichnung' => 'Selbstverwaltung',
                'umlage_typ' => 'Spezial',
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => '3,00',
                'anteil' => $customShare02,
            ],
        ];
    }
}
