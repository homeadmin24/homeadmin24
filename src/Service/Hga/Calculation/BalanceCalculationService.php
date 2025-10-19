<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\Weg;
use App\Repository\MonatsSaldoRepository;

/**
 * Balance calculation service for HGA reports.
 *
 * Handles calculation of account balance changes (Kontostandsentwicklung)
 * for a given year using MonatsSaldo data.
 */
class BalanceCalculationService
{
    public function __construct(
        private MonatsSaldoRepository $monatsSaldoRepository,
    ) {
    }

    /**
     * Get balance data for template usage.
     *
     * @return array<string, mixed>
     */
    public function getBalanceData(Weg $weg, int $year): array
    {
        $startBalance = $this->getYearStartBalance($weg, $year);
        $endBalance = $this->getYearEndBalance($weg, $year);

        if (!$startBalance || !$endBalance) {
            return ['hasData' => false];
        }

        $startAmount = (float) $startBalance->getOpeningBalance();
        $endAmount = (float) $endBalance->getClosingBalance();
        $change = $endAmount - $startAmount;

        return [
            'hasData' => true,
            'startAmount' => $startAmount,
            'endAmount' => $endAmount,
            'change' => $change,
            'year' => $year,
        ];
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
        $monthlyBalances = $this->monatsSaldoRepository->findByWegAndYear($weg, $year);

        if (empty($monthlyBalances)) {
            return '';
        }

        $output = "\nMONATLICHE KONTOENTWICKLUNG $year:\n";
        $output .= str_repeat('-', 90) . "\n";
        $output .= "Monat     | Anfangssaldo | Umsätze      | Endsaldo     | Transaktionen\n";
        $output .= str_repeat('-', 90) . "\n";

        foreach ($monthlyBalances as $balance) {
            $output .= \sprintf(
                "%-9s | %12s € | %12s € | %12s € | %5d\n",
                $balance->getFormattedBalanceMonth(),
                $this->formatAmount($balance->getOpeningBalance()),
                $this->formatAmount($balance->getTransactionSum()),
                $this->formatAmount($balance->getClosingBalance()),
                $balance->getTransactionCount()
            );
        }

        $output .= str_repeat('=', 90) . "\n\n";

        return $output;
    }

    /**
     * Get year start balance (first month opening balance).
     */
    private function getYearStartBalance(Weg $weg, int $year): ?\App\Entity\MonatsSaldo
    {
        return $this->monatsSaldoRepository->findYearStartBalance($weg, $year);
    }

    /**
     * Get year end balance (last month closing balance).
     */
    private function getYearEndBalance(Weg $weg, int $year): ?\App\Entity\MonatsSaldo
    {
        return $this->monatsSaldoRepository->findYearEndBalance($weg, $year);
    }

    /**
     * Format amount for display.
     */
    private function formatAmount(string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }
}
