<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\WegEinheit;
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
            if ('Hausgeld-Zahlung' === $payment->getHauptkategorie()?->getName()) {
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
                if ('Hausgeld-Zahlung' === $payment->getHauptkategorie()?->getName()) {
                    $month = (int) $payment->getDatum()->format('n');
                    $monthlyTotals[$month] += (float) $payment->getBetrag();
                }
            }
        }

        return $monthlyTotals;
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
