<?php

declare(strict_types=1);

namespace App\Service\Hga\Generator;

use App\Entity\WegEinheit;
use App\Service\Hga\HgaServiceInterface;
use App\Service\Hga\ReportGeneratorInterface;

/**
 * Abstract base class for report generators.
 *
 * Provides common functionality for all report format generators.
 */
abstract class AbstractReportGenerator implements ReportGeneratorInterface
{
    public function __construct(
        protected HgaServiceInterface $hgaService,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generateReport(WegEinheit $einheit, int $year, array $options = []): string
    {
        $errors = $this->validateInputs($einheit, $year, $options);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid inputs: ' . implode(', ', $errors));
        }

        // Get complete report data from HGA service
        $reportData = $this->hgaService->generateReportData($einheit, $year);

        // Merge with options
        $reportData['options'] = $options;

        // Generate format-specific output
        return $this->formatReport($reportData);
    }

    /**
     * {@inheritdoc}
     */
    public function validateInputs(WegEinheit $einheit, int $year, array $options = []): array
    {
        // Delegate core validation to HGA service
        return $this->hgaService->validateCalculationInputs($einheit, $year);
    }

    /**
     * Format the report data into the specific output format.
     *
     * @param array<string, mixed> $reportData Complete report data
     *
     * @return string Formatted report content
     */
    abstract protected function formatReport(array $reportData): string;

    /**
     * Format currency values consistently.
     */
    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    /**
     * Format percentage values.
     */
    protected function formatPercentage(float $value): string
    {
        return number_format($value * 100, 1, ',', '.') . '%';
    }

    /**
     * Format date values.
     */
    protected function formatDate(\DateTimeInterface $date): string
    {
        return $date->format('d.m.Y');
    }

    /**
     * Get result text based on balance.
     *
     * @param array<string, string> $texts
     */
    protected function getResultText(float $saldo, array $texts): string
    {
        if ($saldo > 0) {
            return \sprintf($texts['result_nachzahlung'], abs($saldo));
        } elseif ($saldo < 0) {
            return \sprintf($texts['result_guthaben'], abs($saldo));
        }

        return '✅ Ergebnis: Ausgeglichen (0,00 €)';
    }
}
