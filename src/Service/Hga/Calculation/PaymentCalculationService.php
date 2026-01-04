<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\WegEinheit;
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
            if ($payment->getHauptkategorie()?->getName() === 'Hausgeld-Zahlung') {
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
        $count = count($payments);

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
     *   kategorie: string|null
     * }>
     */
    public function getPaymentDetails(WegEinheit $einheit, int $year): array
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByYear($einheit, $year);

        $details = [];
        foreach ($payments as $payment) {
            $details[] = [
                'datum' => $payment->getDatum(),
                'beschreibung' => $payment->getBezeichnung() ?? 'Zahlung',
                'betrag' => (float) $payment->getBetrag(),
                'kategorie' => $payment->getHauptkategorie()?->getName(),
            ];
        }

        return $details;
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
                if ($payment->getHauptkategorie()?->getName() === 'Hausgeld-Zahlung') {
                    $month = (int) $payment->getDatum()->format('n');
                    $monthlyTotals[$month] += (float) $payment->getBetrag();
                }
            }
        }

        return $monthlyTotals;
    }
}
