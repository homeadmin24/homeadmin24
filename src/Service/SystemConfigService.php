<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SystemConfigRepository;
use Psr\Log\LoggerInterface;

/**
 * Centralized service for accessing system configuration.
 * Provides type-safe access to configuration values with caching.
 */
class SystemConfigService
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function __construct(
        private SystemConfigRepository $configRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get configuration value as array.
     *
     * @param array<string, mixed> $default
     *
     * @return array<string, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $value = $this->configRepository->getConfigValue($key);

        if (null === $value) {
            $this->logger->warning('System config not found', ['key' => $key]);

            return $default;
        }

        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Get configuration value as float.
     */
    public function getFloat(string $key, ?float $default = null): ?float
    {
        return $this->configRepository->getConfigFloat($key) ?? $default;
    }

    /**
     * Get configuration value as string.
     */
    public function getString(string $key, ?string $default = null): ?string
    {
        return $this->configRepository->getConfigString($key) ?? $default;
    }

    /**
     * Get configuration value as int.
     */
    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->configRepository->getConfigInt($key) ?? $default;
    }

    /**
     * Check if configuration exists.
     */
    public function has(string $key): bool
    {
        return $this->configRepository->configExists($key);
    }

    /**
     * Get Wirtschaftsplan data for a specific year.
     *
     * @return array<string, mixed>
     */
    public function getWirtschaftsplanData(int $year): array
    {
        $prefix = "wirtschaftsplan.{$year}";

        return [
            'bank_balances' => $this->getArray("{$prefix}.bank_balances", []),
            'planned_expenses' => [
                'umlagefaehig' => $this->getArray("{$prefix}.expenses.umlagefaehig", []),
                'nicht_umlagefaehig' => $this->getArray("{$prefix}.expenses.nicht_umlagefaehig", []),
            ],
            'planned_income' => $this->getArray("{$prefix}.income", []),
        ];
    }

    /**
     * Get balance data for a specific year.
     *
     * @return array<string, mixed>
     */
    public function getBalanceData(int $year): array
    {
        $startData = $this->getArray("balance.{$year}.start", []);
        $endData = $this->getArray("balance.{$year}.end", []);

        if (empty($startData) || empty($endData)) {
            return ['hasData' => false];
        }

        return [
            'hasData' => true,
            'startAmount' => $startData['amount'] ?? 0.0,
            'endAmount' => $endData['amount'] ?? 0.0,
            'change' => ($endData['amount'] ?? 0.0) - ($startData['amount'] ?? 0.0),
            'year' => $year,
        ];
    }

    /**
     * Get HGA section headers.
     *
     * @return array<string, string>
     */
    public function getHgaSectionHeaders(): array
    {
        return $this->getArray('hga.section_headers', [
            'main_title' => 'HAUSGELDABRECHNUNG %s - EINZELABRECHNUNG',
            'owner_info' => 'EIGENTÜMER INFORMATION:',
            'summary' => 'ABRECHNUNGSÜBERSICHT:',
            'calculation' => 'BERECHNUNG DES ANTEILS:',
            'umlageschluessel' => 'UMLAGESCHLÜSSEL:',
            'umlagefaehig' => '1. UMLAGEFÄHIGE KOSTEN (Mieter):',
            'nicht_umlagefaehig' => '2. NICHT UMLAGEFÄHIGE KOSTEN (Mieter):',
            'ruecklagen' => '3. RÜCKLAGENZUFÜHRUNG:',
            'tax_deductible' => 'STEUERBEGÜNSTIGTE LEISTUNGEN nach §35a EStG:',
            'end' => 'ENDE DER HAUSGELDABRECHNUNG',
        ]);
    }

    /**
     * Get HGA standard texts.
     *
     * @return array<string, mixed>
     */
    public function getHgaStandardTexts(): array
    {
        return $this->getArray('hga.standard_texts', []);
    }

    /**
     * Get account category mappings.
     *
     * @return array<string, mixed>
     */
    public function getAccountCategories(): array
    {
        return $this->getArray('hga.account_categories', []);
    }

    /**
     * Clear the configuration cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Set configuration value (admin functionality).
     */
    public function set(string $key, mixed $value, string $category = 'general', ?string $description = null): void
    {
        $this->configRepository->setConfigValue($key, $value, $category, $description);
        unset($this->cache[$key]); // Clear cache for this key
    }
}
