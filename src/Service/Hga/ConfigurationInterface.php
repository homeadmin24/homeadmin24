<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\WegEinheit;

/**
 * Interface for HGA configuration and settings.
 *
 * Provides type-safe access to HGA-specific configuration
 * with clear separation from system configuration.
 */
interface ConfigurationInterface
{
    /**
     * Get monthly advance payment amount for a unit and year.
     */
    public function getMonthlyAmount(WegEinheit $einheit, int $year): ?float;

    /**
     * Get yearly advance payment total for a unit.
     */
    public function getYearlyAdvancePayment(WegEinheit $einheit, int $year): ?float;

    /**
     * Get tax-deductible account numbers.
     *
     * @return string[]
     */
    public function getTaxDeductibleAccounts(): array;

    /**
     * Check if an account is tax-deductible.
     */
    public function isTaxDeductibleAccount(string $accountNumber): bool;

    /**
     * Get section headers for report generation.
     *
     * @return array<string, string>
     */
    public function getSectionHeaders(): array;

    /**
     * Get standard texts for report generation.
     *
     * @return array<string, mixed>
     */
    public function getStandardTexts(): array;

    /**
     * Get Wirtschaftsplan data for budget planning.
     *
     * @return array<string, mixed>
     */
    public function getWirtschaftsplanData(int $year = 2025): array;
}
