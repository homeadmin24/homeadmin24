<?php

declare(strict_types=1);

namespace App\Service\Hga\Configuration;

use App\Entity\WegEinheit;
use App\Repository\KostenkontoRepository;
use App\Repository\WegEinheitVorauszahlungRepository;
use App\Service\Hga\ConfigurationInterface;

/**
 * HGA configuration implementation.
 *
 * Provides centralized configuration access for HGA operations
 * using static config file instead of database.
 */
class HgaConfiguration implements ConfigurationInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(
        private WegEinheitVorauszahlungRepository $vorauszahlungRepository,
        private KostenkontoRepository $kostenkontoRepository,
        string $projectDir,
    ) {
        $this->config = require $projectDir . '/config/hga_config.php';
    }

    /**
     * {@inheritdoc}
     */
    public function getMonthlyAmount(WegEinheit $einheit, int $year): ?float
    {
        return $this->vorauszahlungRepository->getMonthlyAmount($einheit, $year);
    }

    /**
     * {@inheritdoc}
     */
    public function getYearlyAdvancePayment(WegEinheit $einheit, int $year): ?float
    {
        $monthlyAmount = $this->getMonthlyAmount($einheit, $year);

        return $monthlyAmount ? $monthlyAmount * 12 : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaxDeductibleAccounts(): array
    {
        return $this->kostenkontoRepository->getTaxDeductibleAccountNumbers();
    }

    /**
     * {@inheritdoc}
     */
    public function isTaxDeductibleAccount(string $accountNumber): bool
    {
        return $this->kostenkontoRepository->isAccountTaxDeductible($accountNumber);
    }

    /**
     * {@inheritdoc}
     */
    public function getSectionHeaders(): array
    {
        return $this->config['section_headers'];
    }

    /**
     * {@inheritdoc}
     */
    public function getStandardTexts(): array
    {
        return $this->config['standard_texts'];
    }

    /**
     * {@inheritdoc}
     */
    public function getWirtschaftsplanData(int $year = 2025): array
    {
        return [
            'bank_balances' => $this->config['bank_balances'],
            'planned_expenses' => $this->config['planned_expenses'],
            'planned_income' => $this->config['planned_income'],
        ];
    }

    /**
     * Get account categories for report grouping.
     *
     * @return array<string, mixed>
     */
    public function getAccountCategories(): array
    {
        return $this->config['account_categories'];
    }
}
