<?php

declare(strict_types=1);

namespace App\Service\Hga\Configuration;

use App\Entity\WegEinheit;
use App\Repository\KostenkontoRepository;
use App\Repository\WegEinheitVorauszahlungRepository;
use App\Repository\WirtschaftsplanConfigRepository;
use App\Service\Hga\ConfigurationInterface;
use App\Service\SystemConfigService;

/**
 * HGA configuration implementation.
 *
 * Provides centralized configuration access for HGA operations
 * using system_config values.
 */
class HgaConfiguration implements ConfigurationInterface
{
    public function __construct(
        private WegEinheitVorauszahlungRepository $vorauszahlungRepository,
        private KostenkontoRepository $kostenkontoRepository,
        private SystemConfigService $systemConfigService,
        private WirtschaftsplanConfigRepository $wirtschaftsplanConfigRepository,
    ) {
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
        return $this->systemConfigService->getArray('hga.section_headers', []);
    }

    /**
     * {@inheritdoc}
     */
    public function getStandardTexts(): array
    {
        return $this->systemConfigService->getArray('hga.standard_texts', []);
    }

    /**
     * {@inheritdoc}
     */
    public function getWirtschaftsplanData(int $year = 2025): array
    {
        $prefix = "wirtschaftsplan.{$year}";
        $systemConfigData = $this->systemConfigService->getWirtschaftsplanData($year);
        $systemConfigData['balance_overrides'] = $this->systemConfigService->getArray("{$prefix}.balance_overrides", []);
        $config = $this->wirtschaftsplanConfigRepository->findOneBy(['year' => $year]);

        if (!$config) {
            return $systemConfigData;
        }

        $data = $config->getData() ?? [];

        return [
            'bank_balances' => $data['bank_balances'] ?? $systemConfigData['bank_balances'],
            'planned_expenses' => [
                'umlagefaehig' => $data['planned_expenses']['umlagefaehig'] ?? $systemConfigData['planned_expenses']['umlagefaehig'] ?? [],
                'nicht_umlagefaehig' => $data['planned_expenses']['nicht_umlagefaehig'] ?? $systemConfigData['planned_expenses']['nicht_umlagefaehig'] ?? [],
            ],
            'planned_income' => $data['planned_income'] ?? $systemConfigData['planned_income'],
            'balance_overrides' => $data['balance_overrides'] ?? $systemConfigData['balance_overrides'],
        ];
    }
}
