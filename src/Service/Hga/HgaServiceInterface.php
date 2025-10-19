<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\Weg;
use App\Entity\WegEinheit;

/**
 * Main HGA service interface.
 *
 * This is the primary service interface that orchestrates all HGA operations,
 * providing a unified API for Hausgeldabrechnung functionality.
 */
interface HgaServiceInterface
{
    /**
     * Generate complete HGA report data for a unit and year.
     *
     * @param WegEinheit $einheit The unit to calculate for
     * @param int        $year    The calculation year
     *
     * @return array<string, mixed> Complete calculation data
     */
    public function generateReportData(WegEinheit $einheit, int $year): array;

    /**
     * Calculate total costs for a WEG and year.
     *
     * @param Weg $weg  The WEG to calculate for
     * @param int $year The calculation year
     *
     * @return array<string, mixed> Cost breakdown data
     */
    public function calculateTotalCosts(Weg $weg, int $year): array;

    /**
     * Calculate owner-specific costs for a unit.
     *
     * @param WegEinheit $einheit The unit to calculate for
     * @param int        $year    The calculation year
     *
     * @return array<string, mixed> Owner cost data
     */
    public function calculateOwnerCosts(WegEinheit $einheit, int $year): array;

    /**
     * Calculate payment balance for a unit.
     *
     * @param WegEinheit $einheit The unit to calculate for
     * @param int        $year    The calculation year
     *
     * @return array<string, mixed> Payment balance data
     */
    public function calculatePaymentBalance(WegEinheit $einheit, int $year): array;

    /**
     * Calculate tax-deductible amounts for a unit.
     *
     * @param WegEinheit $einheit The unit to calculate for
     * @param int        $year    The calculation year
     *
     * @return array<string, mixed> Tax deductible data
     */
    public function calculateTaxDeductible(WegEinheit $einheit, int $year): array;

    /**
     * Validate HGA calculation inputs.
     *
     * @return array<string> Array of validation errors (empty if valid)
     */
    public function validateCalculationInputs(WegEinheit $einheit, int $year): array;
}
