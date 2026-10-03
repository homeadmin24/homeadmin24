<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\Weg;

/**
 * Balance calculation service for HGA reports.
 *
 * Handles calculation of account balance changes (Kontostandsentwicklung)
 * for a given year.
 */
class BalanceCalculationService
{
    public function __construct()
    {
    }

    /**
     * Get balance data for template usage.
     *
     * @return array<string, mixed>
     */
    public function getBalanceData(Weg $weg, int $year): array
    {
        return ['hasData' => false];
    }

    /**
     * Generate balance section for text output.
     */
    public function generateBalanceSection(Weg $weg, int $year): string
    {
        $balanceData = $this->getBalanceData($weg, $year);

        if (!$balanceData['hasData']) {
            return '';
        }

        $output = "KONTOSTANDSENTWICKLUNG $year:\n";
        $output .= str_repeat('-', 70) . "\n";
        $output .= \sprintf("Kontostand 31.12.%d:                %s €\n",
            $year - 1,
            $this->formatAmount((string) $balanceData['startAmount'])
        );
        $output .= \sprintf("Kontostand 31.12.%d:                %s €\n",
            $year,
            $this->formatAmount((string) $balanceData['endAmount'])
        );
        $output .= \sprintf("Veränderung:                        %s%s €\n",
            $balanceData['change'] < 0 ? '' : '+',
            $this->formatAmount((string) $balanceData['change'])
        );
        $output .= str_repeat('=', 70) . "\n";

        return $output;
    }

    /**
     * Generate monthly balance overview.
     */
    public function generateMonthlyBalanceOverview(Weg $weg, int $year): string
    {
        return '';
    }

    /**
     * Format amount for display.
     */
    private function formatAmount(string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }
}
