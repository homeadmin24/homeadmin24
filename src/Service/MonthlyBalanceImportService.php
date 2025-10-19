<?php

namespace App\Service;

use App\Entity\MonatsSaldo;
use App\Entity\Weg;
use App\Repository\MonatsSaldoRepository;
use App\Repository\WegRepository;
use Doctrine\ORM\EntityManagerInterface;

class MonthlyBalanceImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MonatsSaldoRepository $monatsSaldoRepository,
        private WegRepository $wegRepository,
    ) {
    }

    /**
     * Import balance data from monthly_saldo_report.txt.
     */
    public function importFromSaldoReport(string $filePath, int $wegId = 1): void
    {
        $weg = $this->wegRepository->find($wegId);
        if (!$weg) {
            throw new \InvalidArgumentException("WEG with ID $wegId not found");
        }

        $lines = file($filePath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        $dataStarted = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'Month/Year')) {
                $dataStarted = true;
                continue;
            }

            if (!$dataStarted || str_contains($line, '---') || str_contains($line, 'Total')) {
                continue;
            }

            $this->parseBalanceLine($line, $weg);
        }

        $this->entityManager->flush();
    }

    private function parseBalanceLine(string $line, Weg $weg): void
    {
        // Parse line like: "Jan 2024     |      8961.95 € |  -3120.58 € |      5841.37 € |  23"
        if (!preg_match('/(\w+)\s+(\d{4})\s+\|\s+([+-]?\d+[\d.,]*)\s+€\s+\|\s+([+-]?\d+[\d.,]*)\s+€\s+\|\s+([+-]?\d+[\d.,]*)\s+€\s+\|\s+(\d+)/', $line, $matches)) {
            return;
        }

        $monthName = $matches[1];
        $year = (int) $matches[2];
        $openingBalance = $this->parseAmount($matches[3]);
        $transactionSum = $this->parseAmount($matches[4]);
        $closingBalance = $this->parseAmount($matches[5]);
        $transactionCount = (int) $matches[6];

        $monthNumber = $this->getMonthNumber($monthName);
        $balanceMonth = new \DateTime("$year-$monthNumber-01");

        // Check if balance already exists
        $existingBalance = $this->monatsSaldoRepository->findByWegAndMonth($weg, $balanceMonth);
        if ($existingBalance) {
            $this->updateMonthlyBalance($existingBalance, $openingBalance, $closingBalance, $transactionSum, $transactionCount);
        } else {
            $this->createMonthlyBalance($weg, $balanceMonth, $openingBalance, $closingBalance, $transactionSum, $transactionCount);
        }
    }

    private function parseAmount(string $amount): float
    {
        // Remove spaces and convert format like "8961.95" to float
        $amount = str_replace(' ', '', $amount);

        // Count dots to determine if it's a decimal separator or thousand separator
        $dotCount = mb_substr_count($amount, '.');

        if (1 === $dotCount) {
            // Single dot - check if it's decimal or thousand separator
            $dotPos = mb_strpos($amount, '.');
            $afterDot = mb_substr($amount, $dotPos + 1);

            if (2 === \mb_strlen($afterDot)) {
                // Two digits after dot = decimal separator (e.g., 8961.95)
                return (float) $amount;
            }
            // More than 2 digits = thousand separator (e.g., 8.961)
            $amount = str_replace('.', '', $amount);

            return (float) $amount;
        } elseif ($dotCount > 1) {
            // Multiple dots = thousand separators (e.g., 1.234.567)
            $amount = str_replace('.', '', $amount);

            return (float) $amount;
        }

        // No dots
        return (float) $amount;
    }

    private function getMonthNumber(string $monthName): int
    {
        $months = [
            'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
            'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
            'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
        ];

        return $months[$monthName] ?? 1;
    }

    private function createMonthlyBalance(
        Weg $weg,
        \DateTime $balanceMonth,
        float $openingBalance,
        float $closingBalance,
        float $transactionSum,
        int $transactionCount,
    ): void {
        $monthlyBalance = new MonatsSaldo();
        $monthlyBalance->setWeg($weg);
        $monthlyBalance->setBalanceMonth($balanceMonth);
        $monthlyBalance->setOpeningBalance((string) $openingBalance);
        $monthlyBalance->setClosingBalance((string) $closingBalance);
        $monthlyBalance->setTransactionSum((string) $transactionSum);
        $monthlyBalance->setTransactionCount($transactionCount);

        $this->entityManager->persist($monthlyBalance);
    }

    private function updateMonthlyBalance(
        MonatsSaldo $balance,
        float $openingBalance,
        float $closingBalance,
        float $transactionSum,
        int $transactionCount,
    ): void {
        $balance->setOpeningBalance((string) $openingBalance);
        $balance->setClosingBalance((string) $closingBalance);
        $balance->setTransactionSum((string) $transactionSum);
        $balance->setTransactionCount($transactionCount);
        $balance->setUpdatedAt(new \DateTime());
    }
}
