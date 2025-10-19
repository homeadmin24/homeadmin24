<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\WegEinheit;

/**
 * Interface for HGA report generation.
 *
 * Defines the contract for generating different report formats
 * with consistent API and error handling.
 */
interface ReportGeneratorInterface
{
    /**
     * Generate report for a specific unit and year.
     *
     * @param WegEinheit           $einheit The unit to generate report for
     * @param int                  $year    The year to generate report for
     * @param array<string, mixed> $options Additional options for report generation
     *
     * @throws \InvalidArgumentException If inputs are invalid
     * @throws \RuntimeException         If report generation fails
     *
     * @return string The generated report content
     */
    public function generateReport(WegEinheit $einheit, int $year, array $options = []): string;

    /**
     * Get the MIME type for this report format.
     */
    public function getMimeType(): string;

    /**
     * Get the file extension for this report format.
     */
    public function getFileExtension(): string;

    /**
     * Validate inputs before report generation.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string> Array of validation errors (empty if valid)
     */
    public function validateInputs(WegEinheit $einheit, int $year, array $options = []): array;
}
