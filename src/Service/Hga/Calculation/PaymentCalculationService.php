<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\WegEinheit;
use App\Entity\Zahlungskategorie;
use App\Repository\RechnungRepository;
use App\Repository\ZahlungRepository;
use App\Service\Hga\ConfigurationInterface;

/**
 * Payment calculation service for HGA.
 *
 * Handles all payment-related calculations including advance payments,
 * actual payments, and balance calculations.
 */
class PaymentCalculationService
{
    public function __construct(
        private ZahlungRepository $zahlungRepository,
        private RechnungRepository $rechnungRepository,
        private ConfigurationInterface $configurationService,
    ) {
    }

    /**
     * Calculate total advance payments (Soll) for a unit and year.
     */
    public function calculateAdvancePayments(WegEinheit $einheit, int $year): float
    {
        $yearlyAmount = $this->configurationService->getYearlyAdvancePayment($einheit, $year);

        return $yearlyAmount ?? 0.0;
    }

    /**
     * Calculate total actual Wohngeld payments (Ist) for a unit and year.
     * Only includes payments with category 'Hausgeld-Zahlung'.
     * Excludes Nachzahlungen and Sonderumlagen.
     */
    public function calculateActualPayments(WegEinheit $einheit, int $year): float
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByYear($einheit, $year);

        $total = 0.0;
        foreach ($payments as $payment) {
            // Only count Hausgeld-Zahlung category
            if (Zahlungskategorie::NAME_HAUSGELD_ZAHLUNG === $payment->getHauptkategorie()?->getName()) {
                $total += (float) $payment->getBetrag();
            }
        }

        return $total;
    }

    /**
     * Calculate payment balance for a unit and year.
     *
     * @return array{
     *   soll: float,
     *   ist: float,
     *   differenz: float,
     *   status: string,
     *   count: int
     * }
     */
    public function calculatePaymentBalance(WegEinheit $einheit, int $year): array
    {
        $soll = $this->calculateAdvancePayments($einheit, $year);
        $ist = $this->calculateActualPayments($einheit, $year);
        $differenz = $ist - $soll;

        // Count actual payments
        $payments = $this->zahlungRepository->getOwnerPaymentsByYear($einheit, $year);
        $count = \count($payments);

        return [
            'soll' => $soll,
            'ist' => $ist,
            'differenz' => $differenz,
            'status' => $differenz >= 0 ? 'Überdeckung' : 'Unterdeckung',
            'count' => $count,
        ];
    }

    /**
     * Get detailed payment list for a unit and year.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null,
     *   kostenkonto_nummer: string|null,
     *   kostenkonto_bezeichnung: string|null
     * }>
     */
    public function getPaymentDetails(WegEinheit $einheit, int $year): array
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByYear($einheit, $year);

        $details = [];
        foreach ($payments as $payment) {
            $kostenkonto = $payment->getKostenkonto();
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung(),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
                'kostenkonto_nummer' => $kostenkonto?->getNummer(),
                'kostenkonto_bezeichnung' => $kostenkonto?->getBezeichnung(),
            ];
        }

        return $details;
    }

    /**
     * Get detailed payment list for a unit by payment date (Zufluss-/Abfluss-Prinzip).
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   abrechnungsjahr_zuordnung: string|null,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null
     * }>
     */
    public function getPaymentDetailsByPaymentDate(WegEinheit $einheit, int $year): array
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByPaymentDate($einheit, $year);

        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung() ?? $payment->getDatum()->format('Y'),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
            ];
        }

        return $details;
    }

    /**
     * Get detailed payment list for a unit by payment date in an inclusive range.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   abrechnungsjahr_zuordnung: string|null,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null
     * }>
     */
    public function getPaymentDetailsByPaymentDateRange(WegEinheit $einheit, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByPaymentDateRange($einheit, $startDate, $endDate);

        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung() ?? $payment->getDatum()->format('Y'),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
            ];
        }

        return $details;
    }

    /**
     * Get WEG-level income payments by payment date (not linked to any owner).
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   abrechnungsjahr_zuordnung: int|string,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null
     * }>
     */
    public function getWegLevelIncomeByPaymentDate(\App\Entity\Weg $weg, int $year): array
    {
        $payments = $this->zahlungRepository->getWegLevelIncomeByPaymentDate($weg, $year);

        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung() ?? $payment->getDatum()->format('Y'),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
            ];
        }

        return $details;
    }

    /**
     * Get WEG-level income payments by payment date (not linked to any owner) in an inclusive range.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   abrechnungsjahr_zuordnung: int|string,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null
     * }>
     */
    public function getWegLevelIncomeByPaymentDateRange(\App\Entity\Weg $weg, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $payments = $this->zahlungRepository->getWegLevelIncomeByPaymentDateRange($weg, $startDate, $endDate);

        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung() ?? $payment->getDatum()->format('Y'),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
            ];
        }

        return $details;
    }

    /**
     * Get detailed payment list for a WEG filtered by transaction date year.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null,
     *   kostenkonto_nummer: string|null,
     *   kostenkonto_bezeichnung: string|null
     * }>
     */
    public function getWegPaymentDetails(\App\Entity\Weg $weg, int $year): array
    {
        $payments = $this->zahlungRepository->getPaymentsByWegAndDateYear($weg, $year);

        $details = [];
        foreach ($payments as $payment) {
            $kostenkonto = $payment->getKostenkonto();
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung(),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
                'kostenkonto_nummer' => $kostenkonto?->getNummer(),
                'kostenkonto_bezeichnung' => $kostenkonto?->getBezeichnung(),
            ];
        }

        return $details;
    }

    /**
     * Get detailed payment list for all payments filtered by transaction date year.
     *
     * @param bool $usePaymentDate If true, filter by payment date (01.01-30.12) for Zufluss-/Abfluss.
     *                             If false, filter by abrechnungsjahrZuordnung for periodengerecht.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   beschreibung: string,
     *   betrag: float,
     *   kategorie: string|null,
     *   partner: string|null,
     *   kostenkonto_nummer: string|null,
     *   kostenkonto_bezeichnung: string|null
     * }>
     */
    public function getAllPaymentDetails(int $year, bool $usePaymentDate = false): array
    {
        $payments = $usePaymentDate
            ? $this->zahlungRepository->getAllPaymentsByPaymentDateYear($year)
            : $this->zahlungRepository->getAllPaymentsByDateYear($year);

        $details = [];
        foreach ($payments as $payment) {
            $kostenkonto = $payment->getKostenkonto();
            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr_zuordnung' => $payment->getAbrechnungsjahrZuordnung(),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
                'partner' => $payment->getBuchungspartner(),
                'kostenkonto_nummer' => $kostenkonto?->getNummer(),
                'kostenkonto_bezeichnung' => $kostenkonto?->getBezeichnung(),
            ];
        }

        return $details;
    }

    /**
     * Get expense payment details for a specific date range with abrechnungsjahr grouping.
     *
     * @return array{
     *   payments: array<array{datum: \DateTimeInterface, abrechnungsjahr: string, beschreibung: string, betrag: float, partner: string|null, kostenkonto_nummer: string|null, kostenkonto_bezeichnung: string|null}>,
     *   by_abrechnungsjahr: array<string, array{count: int, total: float}>,
     *   total_count: int,
     *   total_amount: float
     * }
     */
    public function getExpenseDetailsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $payments = $this->zahlungRepository->getAllExpensesByDateRange($startDate, $endDate);

        return $this->buildExpenseDetailsPayload($payments);
    }

    /**
     * Get expense payment details for a WEG/date range based on Vermögensabgrenzung source data.
     * Includes owner-linked and WEG-level payments and keeps Umbuchungen for full traceability.
     *
     * @return array{
     *   payments: array<array{datum: \DateTimeInterface, abrechnungsjahr: string, beschreibung: string, betrag: float, partner: string|null, kostenkonto_nummer: string|null, kostenkonto_bezeichnung: string|null}>,
     *   by_abrechnungsjahr: array<string, array{count: int, total: float}>,
     *   total_count: int,
     *   total_amount: float
     * }
     */
    public function getExpenseDetailsByWegAndDateRange(\App\Entity\Weg $weg, \DateTimeInterface $startDate, \DateTimeInterface $endDate, int $defaultYear, string $bankkontoTyp = 'hausgeld'): array
    {
        $payments = $this->zahlungRepository->findByWegAndDateRange($weg, $startDate, $endDate, $bankkontoTyp);

        // Detail sections for expenses only: keep the same sign split as Vermögensabgrenzung.
        $expensePayments = array_filter(
            $payments,
            static fn ($payment): bool => (float) $payment->getBetrag() < 0
        );

        return $this->buildExpenseDetailsPayload($expensePayments);
    }

    /**
     * Summaries for invoices that are accounted in the year but paid outside the year.
     *
     * @return array{
     *   unpaid_at_year_end: float,
     *   paid_outside_year: float,
     *   invoice_count: int,
     *   unpaid_count: int
     * }
     */
    public function getInvoiceOpenItemsSummary(\App\Entity\Weg $weg, int $year): array
    {
        $rows = $this->rechnungRepository->getInvoiceStatusRowsForWegYear($weg->getId());

        $unpaidAtYearEnd = 0.0;
        $paidOutsideYear = 0.0;
        $invoiceCount = 0;
        $unpaidCount = 0;

        foreach ($rows as $row) {
            $invoiceYear = $this->resolveInvoiceYear($row);
            if ($invoiceYear !== $year) {
                continue;
            }

            ++$invoiceCount;
            $amount = (float) ($row['amount'] ?? 0);
            $paymentDate = $row['paymentDate'] ?? null;
            $paymentYear = $paymentDate instanceof \DateTimeInterface ? (int) $paymentDate->format('Y') : null;
            $isOutstanding = (bool) ($row['outstanding'] ?? false);
            if ($isOutstanding) {
                $paymentYear = null;
            }

            $isUnpaidAtYearEnd = null === $paymentYear || $paymentYear > $year;
            if ($isUnpaidAtYearEnd) {
                $unpaidAtYearEnd += $amount;
                ++$unpaidCount;
            }

            $isPaidOutsideYear = null === $paymentYear || $paymentYear !== $year;
            if ($isPaidOutsideYear) {
                $paidOutsideYear += $amount;
            }
        }

        return [
            'unpaid_at_year_end' => $unpaidAtYearEnd,
            'paid_outside_year' => $paidOutsideYear,
            'invoice_count' => $invoiceCount,
            'unpaid_count' => $unpaidCount,
            'outstanding_count' => $this->countOutstanding($rows, $year),
        ];
    }

    /**
     * Calculate total advance payments for all units in the WEG.
     */
    public function calculateTotalAdvancePaymentsForWeg(WegEinheit $einheit, int $year): float
    {
        $weg = $einheit->getWeg();
        $units = $weg->getEinheiten();

        $total = 0.0;
        foreach ($units as $unit) {
            $total += $this->calculateAdvancePayments($unit, $year);
        }

        return $total;
    }

    /**
     * Calculate total actual payments for all units in the WEG.
     */
    public function calculateTotalActualPaymentsForWeg(WegEinheit $einheit, int $year): float
    {
        $weg = $einheit->getWeg();
        $units = $weg->getEinheiten();

        $total = 0.0;
        foreach ($units as $unit) {
            $total += $this->calculateActualPayments($unit, $year);
        }

        return $total;
    }

    /**
     * Get summed income/expense totals for a calendar year.
     *
     * @return array{income: float, expense: float}
     */
    public function getPaymentTotalsByDateYear(int $year): array
    {
        return $this->zahlungRepository->getPaymentTotalsByDateYear($year);
    }

    /**
     * Get Vermoegen-specific payment summary for a calendar year.
     *
     * @return array<string, float>
     */
    public function getVermoegenPaymentSummary(int $year): array
    {
        return $this->zahlungRepository->getVermoegenPaymentSummary($year);
    }

    /**
     * Get Vermoegen-specific payment summary until a custom end date.
     *
     * @return array<string, float>
     */
    public function getVermoegenPaymentSummaryUntil(int $year, \DateTimeInterface $endDate): array
    {
        return $this->zahlungRepository->getVermoegenPaymentSummary($year, $endDate);
    }

    /**
     * Get monthly advance payment for WEG (sum of all units).
     */
    public function getMonthlyAdvancePaymentForWeg(WegEinheit $einheit, int $year): float
    {
        $weg = $einheit->getWeg();
        $units = $weg->getEinheiten();

        $total = 0.0;
        foreach ($units as $unit) {
            $monthlyAmount = $this->configurationService->getMonthlyAmount($unit, $year);
            $total += $monthlyAmount ?? 0.0;
        }

        return $total;
    }

    /**
     * Get monthly actual WOHNGELD payments grouped by month for WEG.
     * Only includes payments with category 'Hausgeld-Zahlung'.
     *
     * @return array<int, float> Month number => Total amount
     */
    public function getMonthlyActualPaymentsForWeg(WegEinheit $einheit, int $year): array
    {
        $weg = $einheit->getWeg();
        $units = $weg->getEinheiten();

        $monthlyTotals = array_fill(1, 12, 0.0);

        foreach ($units as $unit) {
            $payments = $this->zahlungRepository->getOwnerPaymentsByYear($unit, $year);

            foreach ($payments as $payment) {
                // Only count Hausgeld-Zahlung category
                if (Zahlungskategorie::NAME_HAUSGELD_ZAHLUNG === $payment->getHauptkategorie()?->getName()) {
                    $month = (int) $payment->getDatum()->format('n');
                    $monthlyTotals[$month] += (float) $payment->getBetrag();
                }
            }
        }

        return $monthlyTotals;
    }

    /**
     * @param array<int, \App\Entity\Zahlung> $payments
     *
     * @return array{
     *   payments: array<array{datum: \DateTimeInterface, abrechnungsjahr: string, beschreibung: string, betrag: float, partner: string|null, kostenkonto_nummer: string|null, kostenkonto_bezeichnung: string|null}>,
     *   by_abrechnungsjahr: array<string, array{count: int, total: float}>,
     *   total_count: int,
     *   total_amount: float
     * }
     */
    private function buildExpenseDetailsPayload(array $payments): array
    {
        $payments = array_values($payments);
        usort($payments, static function ($left, $right): int {
            $leftKostenkonto = $left->getKostenkonto();
            $rightKostenkonto = $right->getKostenkonto();

            $leftLabel = $leftKostenkonto
                ? mb_trim((string) $leftKostenkonto->getNummer() . ' ' . (string) $leftKostenkonto->getBezeichnung())
                : 'Ohne Kostenkonto';
            $rightLabel = $rightKostenkonto
                ? mb_trim((string) $rightKostenkonto->getNummer() . ' ' . (string) $rightKostenkonto->getBezeichnung())
                : 'Ohne Kostenkonto';

            $kontoCompare = strcmp($leftLabel, $rightLabel);
            if (0 !== $kontoCompare) {
                return $kontoCompare;
            }

            $dateCompare = $left->getDatum() <=> $right->getDatum();
            if (0 !== $dateCompare) {
                return $dateCompare;
            }

            return ((float) $left->getBetrag()) <=> ((float) $right->getBetrag());
        });

        $details = [];
        $byAbrechnungsjahr = [];
        $totalAmount = 0.0;

        foreach ($payments as $payment) {
            $kostenkonto = $payment->getKostenkonto();
            $abrechnungsjahr = $payment->getAbrechnungsjahrZuordnung() ?? $payment->getDatum()->format('Y');

            $details[] = [
                'datum' => $payment->getDatum(),
                'abrechnungsjahr' => $abrechnungsjahr,
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'partner' => $payment->getBuchungspartner(),
                'kostenkonto_nummer' => $kostenkonto?->getNummer(),
                'kostenkonto_bezeichnung' => $kostenkonto?->getBezeichnung(),
            ];

            if (!isset($byAbrechnungsjahr[$abrechnungsjahr])) {
                $byAbrechnungsjahr[$abrechnungsjahr] = ['count' => 0, 'total' => 0.0];
            }
            ++$byAbrechnungsjahr[$abrechnungsjahr]['count'];
            $byAbrechnungsjahr[$abrechnungsjahr]['total'] += (float) $payment->getBetrag();
            $totalAmount += (float) $payment->getBetrag();
        }

        ksort($byAbrechnungsjahr);

        return [
            'payments' => $details,
            'by_abrechnungsjahr' => $byAbrechnungsjahr,
            'total_count' => \count($details),
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Resolve invoice year for reporting.
     *
     * @param array{
     *   dueDate?: \DateTimeInterface|null,
     *   serviceDate?: \DateTimeInterface|null,
     *   docYear?: int|null,
     *   docUpload?: \DateTimeInterface|null
     * } $row
     */
    private function resolveInvoiceYear(array $row): ?int
    {
        if (!empty($row['docYear'])) {
            return (int) $row['docYear'];
        }
        if (($row['dueDate'] ?? null) instanceof \DateTimeInterface) {
            return (int) $row['dueDate']->format('Y');
        }
        if (($row['serviceDate'] ?? null) instanceof \DateTimeInterface) {
            return (int) $row['serviceDate']->format('Y');
        }
        if (($row['docUpload'] ?? null) instanceof \DateTimeInterface) {
            return (int) $row['docUpload']->format('Y');
        }

        return null;
    }

    /**
     * Count invoices flagged as outstanding for the target year.
     *
     * @param array<int, array{
     *   outstanding?: bool|null,
     *   dueDate?: \DateTimeInterface|null,
     *   serviceDate?: \DateTimeInterface|null,
     *   docYear?: int|null,
     *   docUpload?: \DateTimeInterface|null
     * }> $rows
     */
    private function countOutstanding(array $rows, int $year): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $invoiceYear = $this->resolveInvoiceYear($row);
            if ($invoiceYear !== $year) {
                continue;
            }
            if ((bool) ($row['outstanding'] ?? false)) {
                ++$count;
            }
        }

        return $count;
    }
}
