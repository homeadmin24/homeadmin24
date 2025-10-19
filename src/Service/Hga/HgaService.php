<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Service\Hga\Calculation\BalanceCalculationService;
use App\Service\Hga\Calculation\CostCalculationService;
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

            // Build complete report data structure
            return [
                'einheit' => [
                    'nummer' => $einheit->getNummer(),
                    'beschreibung' => $einheit->getBezeichnung(),
                    'eigentuemer' => $einheit->getMiteigentuemer(),
                    'mea' => $einheit->getMiteigentumsanteile(),
                    'hebeanlage' => $einheit->getHebeanlage(),
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
                'weg_totals' => [
                    'gesamtkosten' => $wegCostTotals['gesamtkosten'],
                    'soll' => $payments['weg_totals']['soll'] ?? 0.0,
                    'ist' => $payments['weg_totals']['ist'] ?? 0.0,
                    'cost_breakdown' => $wegCostTotals,
                ],
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
        $units = $weg->getEinheiten();

        $totalUmlagefaehig = 0.0;
        $totalNichtUmlagefaehig = 0.0;
        $totalRuecklagen = 0.0;

        // Calculate totals for all units
        foreach ($units as $unit) {
            $costs = $this->costCalculationService->calculateTotalCosts($unit, $year);

            $totalUmlagefaehig += $costs['umlagefaehig']['total'];
            $totalNichtUmlagefaehig += $costs['nicht_umlagefaehig']['total'];
            $totalRuecklagen += $costs['ruecklagen']['total'];
        }

        // External costs are handled in the TXT generator display layer
        $externalCostTotals = $this->externalCostService->getTotalExternalCostsForWeg($weg, $year);

        return [
            'umlagefaehig' => $totalUmlagefaehig,
            'nicht_umlagefaehig' => $totalNichtUmlagefaehig,
            'ruecklagen' => $totalRuecklagen,
            'external_costs' => $externalCostTotals,
            'gesamtkosten' => $totalUmlagefaehig + $totalNichtUmlagefaehig + $totalRuecklagen,
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

        return array_merge($balance, [
            'payment_details' => $paymentDetails,
            'weg_totals' => [
                'soll' => $wegSollTotal,
                'ist' => $wegIstTotal,
                'differenz' => $wegIstTotal - $wegSollTotal,
            ],
        ]);
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
}
