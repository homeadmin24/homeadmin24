<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\BankkontoTyp;
use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Entity\Zahlungskategorie;
use App\Repository\UmlageschluesselRepository;
use App\Service\Hga\Calculation\BalanceCalculationService;
use App\Service\Hga\Calculation\CostCalculationService;
use App\Service\Hga\Calculation\DistributionService;
use App\Service\Hga\Calculation\ExternalCostService;
use App\Service\Hga\Calculation\KontostandCalculationService;
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
        private KontostandCalculationService $kontostandCalculationService,
        private ConfigurationInterface $configurationService,
        private UmlageschluesselRepository $umlageschluesselRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generateReportData(WegEinheit $einheit, int $year, string $reportType = 'eigentuemer'): array
    {
        $errors = $this->validateCalculationInputs($einheit, $year);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid inputs: ' . implode(', ', $errors));
        }

        // Determine calculation method based on report type
        $calculationMethod = $this->getCalculationMethod($reportType, $year);

        // Zufluss-/Abfluss-Prinzip (Eigentümer): use payment date
        // Periodengerecht (Mieter): use abrechnungsjahrZuordnung
        $usePaymentDate = ('eigentuemer' === $reportType);

        try {
            // Get all calculation data - both methods for dual display
            $costs = $this->costCalculationService->calculateTotalCosts($einheit, $year, $usePaymentDate);
            $costsMieter = $this->costCalculationService->calculateTotalCosts($einheit, $year, false);
            $costsEigentuemer = $this->costCalculationService->calculateTotalCosts($einheit, $year, true);
            $payments = $this->calculatePaymentBalance($einheit, $year, $usePaymentDate);
            $taxDeductible = $this->calculateTaxDeductible($einheit, $year);
            $taxDeductibleMieter = $this->calculateTaxDeductible($einheit, $year, true);
            $externalCosts = $this->externalCostService->getAllExternalCosts($einheit, $year);
            $balanceData = $this->balanceCalculationService->getBalanceData($einheit->getWeg(), $year);
            $previousBalanceData = $this->balanceCalculationService->getBalanceData($einheit->getWeg(), $year - 1);
            $wirtschaftsplanData = $this->configurationService->getWirtschaftsplanData($year);
            $wirtschaftsplanPlanData = $this->configurationService->getWirtschaftsplanData($year + 1);
            $vermoegenFromConfig = $this->buildVermoegenFromConfig($wirtschaftsplanData, $year);
            $vermoegenOverview = $vermoegenFromConfig['overview'];
            $vermoegenTimeline = $vermoegenFromConfig['timeline'];
            $paymentYearTotals = $this->paymentCalculationService->getPaymentTotalsByDateYear($year);
            $vermoegenPayments = $this->paymentCalculationService->getVermoegenPaymentSummary($year);
            $balanceDate = $this->parseBalanceDate($wirtschaftsplanData['bank_balances'] ?? []);
            $vermoegenPaymentsStichtag = $balanceDate
                ? $this->paymentCalculationService->getVermoegenPaymentSummaryUntil($year, $balanceDate)
                : $vermoegenPayments;
            $vermoegenInvoices = $this->paymentCalculationService->getInvoiceOpenItemsSummary($einheit->getWeg(), $year);
            $prevYear = $year - 1;
            $vermoegenPrevFromConfig = $this->buildPreviousVermoegenFromConfig($wirtschaftsplanData, $prevYear);
            $vermoegenPrevPayments = $this->paymentCalculationService->getVermoegenPaymentSummary($prevYear);
            $vermoegenPrevInvoices = $this->paymentCalculationService->getInvoiceOpenItemsSummary($einheit->getWeg(), $prevYear);

            // Calculate Vermögensabgrenzung (new approach) - both accounts
            $vermoegenAbgrenzungHausgeld = $this->kontostandCalculationService->calculateVermoegensabgrenzung(
                $einheit->getWeg(),
                $year,
                BankkontoTyp::HAUSGELD
            );
            $vermoegenAbgrenzungRuecklage = $this->kontostandCalculationService->calculateVermoegensabgrenzung(
                $einheit->getWeg(),
                $year,
                BankkontoTyp::RUECKLAGE
            );

            // Get expense details by period (for detail pages)
            $expensesByPeriod = $this->getExpensesByPeriod($einheit->getWeg(), $vermoegenAbgrenzungHausgeld, $year, BankkontoTyp::HAUSGELD->value);
            $payments['expenses_abrechnung'] = $expensesByPeriod['abrechnung'];
            $payments['expenses_abgrenzung'] = $expensesByPeriod['abgrenzung'];
            $incomeByPeriod = $this->getIncomeByPeriod($einheit, $vermoegenAbgrenzungHausgeld, $year);
            $payments['income_abrechnung'] = $incomeByPeriod['abrechnung'];
            $payments['income_abgrenzung'] = $incomeByPeriod['abgrenzung'];

            $wirtschaftsplanPlanData = $this->applyPlannedIncomeDefaults($wirtschaftsplanPlanData);

            // Calculate Nachzahlungen/Guthaben from actual HGA results for all units
            $nachzahlungenDetails = $this->calculateWegNachzahlungen($einheit->getWeg(), $year, $usePaymentDate);
            $wirtschaftsplanPlanData['planned_income']['nachzahlungen_' . $year] = $nachzahlungenDetails['total'];
            $wirtschaftsplanPlanData['planned_income']['nachzahlungen_details'] = $nachzahlungenDetails['details'];
            $wirtschaftsplanPlanData['planned_income']['nachzahlungen_source'] = 'calculated';

            // Get WEG totals - use payment date filtering for Eigentümer
            $wegCostTotals = $this->costCalculationService->calculateTotalCostsForWeg($einheit->getWeg(), $year, $usePaymentDate);

            // Calculate final totals for display (BGH V ZR 44/09 compliant)
            $calculatedTotals = $this->calculateFinalTotals($costs, $externalCosts, $payments);

            // Generate Umlageschlüssel data
            $umlageschluessel = $this->generateUmlageschluesselData($einheit, $year);

            // Build complete report data structure
            return [
                'reportType' => $reportType,
                'calculationMethod' => $calculationMethod,
                'einheit' => [
                    'nummer' => $einheit->getNummer(),
                    'beschreibung' => $einheit->getBezeichnung(),
                    'eigentuemer' => $einheit->getMiteigentuemer(),
                    'mieter' => $einheit->getMieter(),
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
                'costs_mieter' => $costsMieter,
                'costs_eigentuemer' => $costsEigentuemer,
                'payments' => $payments,
                'tax_deductible' => $taxDeductible,
                'tax_deductible_mieter' => $taxDeductibleMieter,
                'external_costs' => $externalCosts,
                'balance' => $balanceData,
                'balance_previous' => $previousBalanceData,
                'vermoegen' => $vermoegenOverview,
                'vermoegen_timeline' => $vermoegenTimeline,
                'vermoegen_payments' => $vermoegenPayments,
                'vermoegen_payments_stichtag' => $vermoegenPaymentsStichtag,
                'vermoegen_invoices' => $vermoegenInvoices,
                'vermoegen_previous' => [
                    'year' => $prevYear,
                    'overview' => $vermoegenPrevFromConfig['overview'],
                    'timeline' => $vermoegenPrevFromConfig['timeline'],
                    'payments' => $vermoegenPrevPayments,
                    'invoices' => $vermoegenPrevInvoices,
                ],
                'vermoegen_payments_raw' => $paymentYearTotals,
                'vermoegen_abgrenzung' => [
                    'hausgeld' => $vermoegenAbgrenzungHausgeld,
                    'ruecklage' => $vermoegenAbgrenzungRuecklage,
                ],
                'wirtschaftsplan' => $wirtschaftsplanPlanData,
                'umlageschluessel' => $umlageschluessel,
                'weg_totals' => [
                    'gesamtkosten' => $wegCostTotals['gesamtkosten'],
                    'soll' => $payments['weg_totals']['soll'] ?? 0.0,
                    'ist' => $payments['weg_totals']['ist'] ?? 0.0,
                    'cost_breakdown' => $wegCostTotals,
                ],
                'calculated_totals' => $calculatedTotals,
                'calculation_date' => new \DateTime(),
                'income_categories' => Zahlungskategorie::INCOME_CATEGORIES,
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
    public function calculatePaymentBalance(WegEinheit $einheit, int $year, bool $usePaymentDate = false): array
    {
        $balance = $this->paymentCalculationService->calculatePaymentBalance($einheit, $year);
        $paymentDetails = $this->paymentCalculationService->getPaymentDetails($einheit, $year);
        $paymentDetailsByDate = $this->paymentCalculationService->getPaymentDetailsByPaymentDate($einheit, $year);
        $allPaymentDetails = $this->paymentCalculationService->getAllPaymentDetails($year, $usePaymentDate);

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
            if (Zahlungskategorie::NAME_HAUSGELD_ZAHLUNG === $kategorie) {
                $monthlyUnitPayments[$month]['wohngeld'] += $payment['betrag'];
            } else {
                $monthlyUnitPayments[$month]['other'][] = $payment;
            }
        }

        // Calculate WEG totals for other payments by category
        $wegCategoryTotals = $this->getWegOtherPayments($einheit->getWeg(), $year);

        // Calculate WEG totals by payment date (for ZAHLUNGSÜBERSICHT EINNAHMEN)
        $wegCategoryTotalsByDate = $this->getWegPaymentsByDate($einheit->getWeg(), $year);
        $wegEinnahmenByDate = array_sum($wegCategoryTotalsByDate);

        // Get WEG-level income payment details (not linked to any owner)
        $wegLevelIncomeDetails = $this->paymentCalculationService->getWegLevelIncomeByPaymentDate($einheit->getWeg(), $year);

        // Calculate unit's total income by payment date
        $unitEinnahmenByDate = array_sum(array_column($paymentDetailsByDate, 'betrag'));

        // For ZAHLUNGSÜBERSICHT display: use monthly SOLL values for consistent display
        // Create array with SOLL value for all 12 months
        $monthlyWegIstDisplay = array_fill(1, 12, $monthlyWegSoll);

        return array_merge($balance, [
            'payment_details' => $paymentDetails,
            'payment_details_by_date' => $paymentDetailsByDate,
            'all_payment_details' => $allPaymentDetails,
            'monthly_unit_payments' => $monthlyUnitPayments,
            'weg_category_totals' => $wegCategoryTotals,
            'weg_category_totals_by_date' => $wegCategoryTotalsByDate,
            'weg_level_income_details' => $wegLevelIncomeDetails,
            'weg_totals' => [
                'soll' => $wegSollTotal,
                'ist' => $wegIstTotal,
                'differenz' => $wegIstTotal - $wegSollTotal,
                'ruecklagen' => $wegRuecklagenTotal,
                'monthly_weg_soll' => $monthlyWegSoll,
                'monthly_unit_soll' => $monthlyUnitSoll,
                'monthly_weg_ist' => $monthlyWegIstDisplay,
                'einnahmen_by_date' => $wegEinnahmenByDate,
                'unit_einnahmen_by_date' => $unitEinnahmenByDate,
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function calculateTaxDeductible(WegEinheit $einheit, int $year, bool $onlyUmlagefaehig = false): array
    {
        return $this->taxCalculationService->calculateTaxDeductible($einheit, $year, $onlyUmlagefaehig);
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
     * Ensure planned income has a monthly total, derived from planned costs if missing.
     *
     * @param array<string, mixed> $wirtschaftsplanData
     *
     * @return array<string, mixed>
     */
    private function applyPlannedIncomeDefaults(array $wirtschaftsplanData): array
    {
        $plannedIncome = $wirtschaftsplanData['planned_income'] ?? [];
        if (isset($plannedIncome['monthly_total'])) {
            $plannedIncome['monthly_total_final'] = (float) $plannedIncome['monthly_total'];
            $plannedIncome['monthly_total_source'] = 'config';
            $wirtschaftsplanData['planned_income'] = $plannedIncome;

            return $wirtschaftsplanData;
        }

        $umlagefaehig = $wirtschaftsplanData['planned_expenses']['umlagefaehig'] ?? [];
        $nichtUmlagefaehig = $wirtschaftsplanData['planned_expenses']['nicht_umlagefaehig'] ?? [];

        $totalPlanned = 0.0;
        foreach ([$umlagefaehig, $nichtUmlagefaehig] as $expenses) {
            foreach ($expenses as $expense) {
                $includeInTotal = $expense['include_in_total'] ?? true;
                if ($includeInTotal) {
                    $totalPlanned += (float) ($expense['amount'] ?? 0);
                }
            }
        }

        $monthlyTotal = $totalPlanned > 0 ? $totalPlanned / 12 : 0.0;
        $plannedIncome['monthly_total_final'] = $monthlyTotal;
        $plannedIncome['monthly_total_source'] = 'calculated';
        $wirtschaftsplanData['planned_income'] = $plannedIncome;

        return $wirtschaftsplanData;
    }

    /**
     * Calculate Nachzahlungen/Guthaben for all units in a WEG.
     *
     * Computes the Saldo (Gesamtkosten - Ist) for each unit and returns
     * per-unit details plus the WEG total. Positive = Nachzahlung, Negative = Guthaben.
     *
     * @return array{total: float, details: array<int, array{nummer: string, eigentuemer: string, saldo: float, is_guthaben: bool}>}
     */
    private function calculateWegNachzahlungen(Weg $weg, int $year, bool $usePaymentDate = true): array
    {
        $details = [];
        $total = 0.0;

        foreach ($weg->getEinheiten() as $unit) {
            $costs = $this->costCalculationService->calculateTotalCosts($unit, $year, $usePaymentDate);
            $externalCosts = $this->externalCostService->getAllExternalCosts($unit, $year);
            $payments = $this->calculatePaymentBalance($unit, $year, $usePaymentDate);
            $totals = $this->calculateFinalTotals($costs, $externalCosts, $payments);

            $saldo = $totals['balance']['saldo'];
            $total += $saldo;

            $details[] = [
                'nummer' => $unit->getNummer(),
                'eigentuemer' => $unit->getMiteigentuemer(),
                'saldo' => $saldo,
                'is_guthaben' => $saldo < 0,
            ];
        }

        return [
            'total' => $total,
            'details' => $details,
        ];
    }

    /**
     * Build Vermoegen overview/timeline from config overrides.
     *
     * @param array<string, mixed> $wirtschaftsplanData
     *
     * @return array{overview: array<string, mixed>, timeline: array<string, mixed>}
     */
    private function buildVermoegenFromConfig(array $wirtschaftsplanData, int $year): array
    {
        $overrides = $wirtschaftsplanData['balance_overrides'] ?? [];

        $prevYearBalances = $this->getVermoegenYearBalances($overrides, $year - 2);
        $startYearBalances = $this->getVermoegenYearBalances($overrides, $year - 1);
        $currentYearBalances = $this->getVermoegenYearBalances($overrides, $year);

        $prevHausgeld = $prevYearBalances['hausgeld_end'];
        $prevRuecklage = $prevYearBalances['ruecklage_end'];
        $startHausgeld = $startYearBalances['hausgeld_end'];
        $startRuecklage = $startYearBalances['ruecklage_end'];
        $endHausgeld = $currentYearBalances['hausgeld_end'];
        $endRuecklage = $currentYearBalances['ruecklage_end'];

        if (null !== $currentYearBalances['hausgeld_start'] || null !== $currentYearBalances['ruecklage_start']) {
            $startHausgeld = $currentYearBalances['hausgeld_start'];
            $startRuecklage = $currentYearBalances['ruecklage_start'];
        }

        $hasData = null !== $prevHausgeld
            || null !== $prevRuecklage
            || null !== $startHausgeld
            || null !== $startRuecklage
            || null !== $endHausgeld
            || null !== $endRuecklage;

        $years = [$year - 2, $year - 1, $year];

        $timeline = [
            'hasData' => $hasData,
            'years' => $years,
            'accounts' => [
                'hausgeld' => [
                    'label' => 'Kontostand Hausgeld',
                    'values' => [
                        $years[0] => $prevHausgeld,
                        $years[1] => $startHausgeld,
                        $years[2] => $endHausgeld,
                    ],
                ],
                'ruecklage' => [
                    'label' => 'Kontostand Rücklage',
                    'values' => [
                        $years[0] => $prevRuecklage,
                        $years[1] => $startRuecklage,
                        $years[2] => $endRuecklage,
                    ],
                ],
                'gesamt' => [
                    'label' => 'Gesamt',
                    'values' => [
                        $years[0] => (null !== $prevHausgeld && null !== $prevRuecklage) ? (float) $prevHausgeld + (float) $prevRuecklage : null,
                        $years[1] => (null !== $startHausgeld && null !== $startRuecklage) ? (float) $startHausgeld + (float) $startRuecklage : null,
                        $years[2] => (null !== $endHausgeld && null !== $endRuecklage) ? (float) $endHausgeld + (float) $endRuecklage : null,
                    ],
                ],
            ],
        ];

        $overview = [
            'hasData' => $hasData,
            'year' => $year,
            'bank_balances' => $wirtschaftsplanData['bank_balances'] ?? [],
            'accounts' => [
                'hausgeld' => [
                    'label' => 'Kontostand Hausgeld',
                    'start' => $startHausgeld,
                    'end' => $endHausgeld,
                    'change' => (null !== $startHausgeld && null !== $endHausgeld) ? (float) $endHausgeld - (float) $startHausgeld : null,
                ],
                'ruecklage' => [
                    'label' => 'Kontostand Rücklage',
                    'start' => $startRuecklage,
                    'end' => $endRuecklage,
                    'change' => (null !== $startRuecklage && null !== $endRuecklage) ? (float) $endRuecklage - (float) $startRuecklage : null,
                ],
                'gesamt' => [
                    'label' => 'Gesamt',
                    'start' => (null !== $startHausgeld && null !== $startRuecklage) ? (float) $startHausgeld + (float) $startRuecklage : null,
                    'end' => (null !== $endHausgeld && null !== $endRuecklage) ? (float) $endHausgeld + (float) $endRuecklage : null,
                    'change' => (null !== $startHausgeld && null !== $startRuecklage && null !== $endHausgeld && null !== $endRuecklage)
                        ? ((float) $endHausgeld + (float) $endRuecklage) - ((float) $startHausgeld + (float) $startRuecklage)
                        : null,
                ],
            ],
        ];

        return [
            'overview' => $overview,
            'timeline' => $timeline,
        ];
    }

    /**
     * @param array<string, mixed> $bankBalances
     */
    private function parseBalanceDate(array $bankBalances): ?\DateTimeInterface
    {
        $balanceDateRaw = $bankBalances['balance_date'] ?? null;
        if (!$balanceDateRaw) {
            return null;
        }

        $balanceDate = \DateTime::createFromFormat('d.m.Y', (string) $balanceDateRaw);

        return $balanceDate instanceof \DateTimeInterface ? $balanceDate : null;
    }

    /**
     * Build Vermoegen overview for the previous year from current-year overrides.
     *
     * @param array<string, mixed> $wirtschaftsplanData
     *
     * @return array{overview: array<string, mixed>, timeline: array<string, mixed>}
     */
    private function buildPreviousVermoegenFromConfig(array $wirtschaftsplanData, int $year): array
    {
        $overrides = $wirtschaftsplanData['balance_overrides'] ?? [];

        $balances = $this->getVermoegenYearBalances($overrides, $year);

        $startHausgeld = $balances['hausgeld_start'];
        $startRuecklage = $balances['ruecklage_start'];
        $endHausgeld = $balances['hausgeld_end'];
        $endRuecklage = $balances['ruecklage_end'];

        $hasData = null !== $startHausgeld
            || null !== $startRuecklage
            || null !== $endHausgeld
            || null !== $endRuecklage;

        $overview = [
            'hasData' => $hasData,
            'year' => $year,
            'accounts' => [
                'hausgeld' => [
                    'label' => 'Kontostand Hausgeld',
                    'start' => $startHausgeld,
                    'end' => $endHausgeld,
                    'change' => (null !== $startHausgeld && null !== $endHausgeld) ? (float) $endHausgeld - (float) $startHausgeld : null,
                ],
                'ruecklage' => [
                    'label' => 'Kontostand Rücklage',
                    'start' => $startRuecklage,
                    'end' => $endRuecklage,
                    'change' => (null !== $startRuecklage && null !== $endRuecklage) ? (float) $endRuecklage - (float) $startRuecklage : null,
                ],
                'gesamt' => [
                    'label' => 'Gesamt',
                    'start' => (null !== $startHausgeld && null !== $startRuecklage) ? (float) $startHausgeld + (float) $startRuecklage : null,
                    'end' => (null !== $endHausgeld && null !== $endRuecklage) ? (float) $endHausgeld + (float) $endRuecklage : null,
                    'change' => (null !== $startHausgeld && null !== $startRuecklage && null !== $endHausgeld && null !== $endRuecklage)
                        ? ((float) $endHausgeld + (float) $endRuecklage) - ((float) $startHausgeld + (float) $startRuecklage)
                        : null,
                ],
            ],
        ];

        return [
            'overview' => $overview,
            'timeline' => [
                'hasData' => false,
                'years' => [],
                'accounts' => [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{hausgeld_start: ?float, hausgeld_end: ?float, ruecklage_start: ?float, ruecklage_end: ?float}
     */
    private function getVermoegenYearBalances(array $overrides, int $year): array
    {
        $yearKey = (string) $year;
        $yearData = $overrides[$yearKey] ?? $overrides[$year] ?? null;

        if (\is_array($yearData)) {
            return [
                'hausgeld_start' => $this->readAccountAmount($yearData, 'hausgeld', 'start'),
                'hausgeld_end' => $this->readAccountAmount($yearData, 'hausgeld', 'end'),
                'ruecklage_start' => $this->readAccountAmount($yearData, 'ruecklagen', 'start'),
                'ruecklage_end' => $this->readAccountAmount($yearData, 'ruecklagen', 'end'),
            ];
        }

        return [
            'hausgeld_start' => null,
            'hausgeld_end' => null,
            'ruecklage_start' => null,
            'ruecklage_end' => null,
        ];
    }

    /**
     * @param array<string, mixed> $yearData
     */
    private function readAccountAmount(array $yearData, string $accountKey, string $field): ?float
    {
        $accountData = $yearData[$accountKey] ?? $yearData[str_replace('ruecklagen', 'ruecklage', $accountKey)] ?? null;
        if (!\is_array($accountData)) {
            return null;
        }

        if (!\array_key_exists($field, $accountData)) {
            return null;
        }

        return null === $accountData[$field] ? null : (float) $accountData[$field];
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
                if (Zahlungskategorie::NAME_HAUSGELD_ZAHLUNG !== $kategorie) {
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
     * Get WEG-wide payment totals by payment date (Zufluss-/Abfluss-Prinzip).
     *
     * @return array<string, float> Kategorie => WEG total
     */
    private function getWegPaymentsByDate(Weg $weg, int $year, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $units = $weg->getEinheiten();

        $categoryTotals = [];

        // Add owner-linked payments
        foreach ($units as $unit) {
            $payments = ($startDate && $endDate)
                ? $this->paymentCalculationService->getPaymentDetailsByPaymentDateRange($unit, $startDate, $endDate)
                : $this->paymentCalculationService->getPaymentDetailsByPaymentDate($unit, $year);

            foreach ($payments as $payment) {
                $kategorie = $payment['kategorie'] ?? 'Unbekannt';
                if (!isset($categoryTotals[$kategorie])) {
                    $categoryTotals[$kategorie] = 0.0;
                }
                $categoryTotals[$kategorie] += $payment['betrag'];
            }
        }

        // Add WEG-level payments (not linked to any owner)
        $wegLevelPayments = ($startDate && $endDate)
            ? $this->paymentCalculationService->getWegLevelIncomeByPaymentDateRange($weg, $startDate, $endDate)
            : $this->paymentCalculationService->getWegLevelIncomeByPaymentDate($weg, $year);
        foreach ($wegLevelPayments as $payment) {
            $kategorie = $payment['kategorie'] ?? 'Unbekannt';
            if (!isset($categoryTotals[$kategorie])) {
                $categoryTotals[$kategorie] = 0.0;
            }
            $categoryTotals[$kategorie] += $payment['betrag'];
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
     * Calculate final totals for ABRECHNUNGSÜBERSICHT and display.
     *
     * This centralizes the calculation logic that was previously duplicated
     * in pdf_report.html.twig.
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

        $nichtUmlagefaehigWegTotal = ($externalCosts['co2']['total'] ?? 0.0);
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

        $nichtUmlagefaehigUnitTotal = ($externalCosts['co2']['unit_share'] ?? 0.0);
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
        $weg = $einheit->getWeg();
        $unitCount = (float) \count($weg->getEinheiten());

        // Parse Hebeanlage fraction (e.g. "2/6" → numerator = 2)
        $hebeanlageShare = 0.0;
        $hebeanlageString = $einheit->getHebeanlage();
        if ($hebeanlageString && preg_match('/^(\d+)\/(\d+)$/', $hebeanlageString, $hm)) {
            $hebeanlageShare = (float) $hm[1];
        }

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

        // Load Umlageschlüssel from database in correct order
        $allSchluessel = $this->umlageschluesselRepository->findAll();
        $hgaOrder = ['01*', '02*', '03*', '04*', '05*', '06*', '07*'];
        usort($allSchluessel, static function ($a, $b) use ($hgaOrder) {
            $posA = array_search($a->getSchluessel(), $hgaOrder, true);
            $posB = array_search($b->getSchluessel(), $hgaOrder, true);
            if (false === $posA) {
                $posA = 999;
            }
            if (false === $posB) {
                $posB = 999;
            }

            return $posA <=> $posB;
        });

        $result = [];
        foreach ($allSchluessel as $schluessel) {
            // Skip deprecated/unused keys
            if ('07*' === $schluessel->getSchluessel()) {
                continue;
            }

            // Get gesamtumlage and umlageTyp from database
            $gesamtumlage = $schluessel->getGesamtumlage() ?? 'Beträge siehe Ergebnisliste';
            $umlageTyp = $schluessel->getUmlageTyp() ?? '€ Festbetrag';

            // Calculate unit-specific anteil based on key
            $anteil = null;

            switch ($schluessel->getSchluessel()) {
                case '01*': // Heiz-/Wasserkosten - from external calculations
                    $anteil = null;
                    break;

                case '02*': // Selbstverwaltung - custom distribution
                    $anteil = $customShare02;
                    break;

                case '03*': // Anzahl Einheit - equal per unit
                    $anteil = 1.0;
                    // Override gesamtumlage with dynamic unit count
                    $gesamtumlage = (string) $unitCount;
                    break;

                case '04*': // Festumlage - fixed amounts per cost account
                    $anteil = null;
                    break;

                case '05*': // MEA - ownership percentage
                    $anteil = $mea * 1000;
                    break;

                case '06*': // Hebeanlage - special distribution
                    $anteil = $hebeanlageShare;
                    break;
            }

            $result[] = [
                'nummer' => $schluessel->getSchluessel(),
                'bezeichnung' => $schluessel->getBezeichnung(),
                'umlage_typ' => $umlageTyp,
                'zeitraum' => $year,
                'tage' => 365,
                'gesamtumlage' => $gesamtumlage,
                'anteil' => $anteil,
            ];
        }

        return $result;
    }

    /**
     * Get expense details by period (Abrechnungsperiode and Abgrenzung).
     *
     * @param array<string, mixed> $vermoegenAbgrenzung Vermögensabgrenzung data (from KontostandCalculationService)
     *
     * @return array{abrechnung: array<string, mixed>, abgrenzung: array<string, mixed>}
     */
    private function getExpensesByPeriod(Weg $weg, array $vermoegenAbgrenzung, int $year, string $bankkontoTyp = 'hausgeld'): array
    {
        $emptyResult = [
            'payments' => [],
            'by_abrechnungsjahr' => [],
            'total_count' => 0,
            'total_amount' => 0.0,
            'start_date' => null,
            'end_date' => null,
        ];

        if (!($vermoegenAbgrenzung['available'] ?? false)) {
            return ['abrechnung' => $emptyResult, 'abgrenzung' => $emptyResult];
        }

        // Get dates from Vermögensabgrenzung
        $abrechnungStart = isset($vermoegenAbgrenzung['periode_abrechnung']['start'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abrechnung']['start'])
            : new \DateTime($year . '-01-01');
        $abrechnungEnd = isset($vermoegenAbgrenzung['periode_abrechnung']['end'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abrechnung']['end'])
            : new \DateTime($year . '-12-30');

        $abgrenzungStart = isset($vermoegenAbgrenzung['periode_abgrenzung']['start'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abgrenzung']['start'])
            : (clone $abrechnungEnd)->modify('+1 day');
        $abgrenzungEnd = isset($vermoegenAbgrenzung['periode_abgrenzung']['end'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abgrenzung']['end'])
            : null;

        // Get expense details for Abrechnungsperiode
        $abrechnungData = $this->paymentCalculationService->getExpenseDetailsByWegAndDateRange(
            $weg,
            $abrechnungStart,
            $abrechnungEnd,
            $year,
            $bankkontoTyp
        );
        $abrechnungData['start_date'] = $abrechnungStart;
        $abrechnungData['end_date'] = $abrechnungEnd;

        // Get expense details for Abgrenzung (only if we have an end date)
        $abgrenzungData = $emptyResult;
        if ($abgrenzungEnd && $abgrenzungEnd > $abrechnungEnd) {
            $abgrenzungData = $this->paymentCalculationService->getExpenseDetailsByWegAndDateRange(
                $weg,
                $abgrenzungStart,
                $abgrenzungEnd,
                $year,
                $bankkontoTyp
            );
            $abgrenzungData['start_date'] = $abgrenzungStart;
            $abgrenzungData['end_date'] = $abgrenzungEnd;
        }

        return [
            'abrechnung' => $abrechnungData,
            'abgrenzung' => $abgrenzungData,
        ];
    }

    /**
     * Get income details by period (Abrechnungsperiode and Abgrenzung).
     *
     * @param array<string, mixed> $vermoegenAbgrenzung Vermögensabgrenzung data (from KontostandCalculationService)
     *
     * @return array{abrechnung: array<string, mixed>, abgrenzung: array<string, mixed>}
     */
    private function getIncomeByPeriod(WegEinheit $einheit, array $vermoegenAbgrenzung, int $year): array
    {
        $emptyResult = [
            'unit_payments' => [],
            'weg_category_totals' => [],
            'weg_level_income_details' => [],
            'total_unit_amount' => 0.0,
            'total_weg_amount' => 0.0,
            'start_date' => null,
            'end_date' => null,
        ];

        if (!($vermoegenAbgrenzung['available'] ?? false)) {
            return ['abrechnung' => $emptyResult, 'abgrenzung' => $emptyResult];
        }

        $abrechnungStart = isset($vermoegenAbgrenzung['periode_abrechnung']['start'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abrechnung']['start'])
            : new \DateTime($year . '-01-01');
        $abrechnungEnd = isset($vermoegenAbgrenzung['periode_abrechnung']['end'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abrechnung']['end'])
            : new \DateTime($year . '-12-30');

        $abgrenzungStart = isset($vermoegenAbgrenzung['periode_abgrenzung']['start'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abgrenzung']['start'])
            : (clone $abrechnungEnd)->modify('+1 day');
        $abgrenzungEnd = isset($vermoegenAbgrenzung['periode_abgrenzung']['end'])
            ? new \DateTime($vermoegenAbgrenzung['periode_abgrenzung']['end'])
            : null;

        $buildResult = function (\DateTimeInterface $startDate, \DateTimeInterface $endDate) use ($einheit, $year): array {
            $unitPayments = $this->paymentCalculationService->getPaymentDetailsByPaymentDateRange($einheit, $startDate, $endDate);
            $wegCategoryTotals = $this->getWegPaymentsByDate($einheit->getWeg(), $year, $startDate, $endDate);
            $wegLevelIncomeDetails = $this->paymentCalculationService->getWegLevelIncomeByPaymentDateRange($einheit->getWeg(), $startDate, $endDate);

            return [
                'unit_payments' => $unitPayments,
                'weg_category_totals' => $wegCategoryTotals,
                'weg_level_income_details' => $wegLevelIncomeDetails,
                'total_unit_amount' => array_sum(array_column($unitPayments, 'betrag')),
                'total_weg_amount' => array_sum($wegCategoryTotals),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        };

        $abrechnungData = $buildResult($abrechnungStart, $abrechnungEnd);
        $abgrenzungData = $emptyResult;

        if ($abgrenzungEnd && $abgrenzungEnd > $abrechnungEnd) {
            $abgrenzungData = $buildResult($abgrenzungStart, $abgrenzungEnd);
        }

        return [
            'abrechnung' => $abrechnungData,
            'abgrenzung' => $abgrenzungData,
        ];
    }

    /**
     * Get calculation method info based on report type.
     *
     * @return array{name: string, periodLabel: string, legalBasis: string}
     */
    private function getCalculationMethod(string $reportType, int $year): array
    {
        return match ($reportType) {
            'mieter' => [
                'name' => 'Mieter-Abrechnung periodengerecht',
                'periodLabel' => \sprintf('01.01.%d - 31.12.%d', $year, $year),
                'legalBasis' => '§ 556 BGB, BetrKV',
            ],
            default => [
                'name' => 'Zufluss-/Abfluss-Prinzip',
                'periodLabel' => \sprintf('01.01.%d - 30.12.%d', $year, $year),
                'legalBasis' => 'BGH V ZR 271/12',
            ],
        };
    }
}
