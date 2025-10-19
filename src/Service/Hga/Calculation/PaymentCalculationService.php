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
     * Calculate total actual payments (Ist) for a unit and year.
     */
    public function calculateActualPayments(WegEinheit $einheit, int $year): float
    {
        $payments = $this->zahlungRepository->getOwnerPaymentsByYear($einheit, $year);

        $total = 0.0;
        foreach ($payments as $payment) {
            $total += (float) $payment->getBetrag();
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
     *   status: string
     * }
     */
    public function calculatePaymentBalance(WegEinheit $einheit, int $year): array
    {
        $soll = $this->calculateAdvancePayments($einheit, $year);
        $ist = $this->calculateActualPayments($einheit, $year);
        $differenz = $ist - $soll;

        return [
            'soll' => $soll,
            'ist' => $ist,
            'differenz' => $differenz,
            'status' => $differenz >= 0 ? 'Überdeckung' : 'Unterdeckung',
        ];
    }

    /**
     * Get detailed payment list for a unit and year.
     *
     * @return array<array{
     *   datum: \DateTimeInterface,
     *   beschreibung: string,
     *   betrag: float
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
}
