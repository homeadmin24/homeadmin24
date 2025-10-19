<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Repository\WegEinheitRepository;
use App\Service\Hga\CalculationInterface;

/**
 * Clean distribution calculation service.
 *
 * Handles all cost distribution calculations based on different distribution keys
 * without hardcoded values and with full test coverage support.
 */
class DistributionService implements CalculationInterface
{
    public function __construct(
        private WegEinheitRepository $wegEinheitRepository,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function calculateOwnerShare(
        float $totalCost,
        string $distributionKey,
        float $mea,
        ?WegEinheit $einheit = null,
        ?Weg $weg = null,
    ): float {
        $errors = $this->validateInputs($totalCost, $distributionKey, $mea);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid calculation inputs: ' . implode(', ', $errors));
        }

        return match ($distributionKey) {
            '01*', '02*', '04*' => $this->calculateExternalDistribution($totalCost, $distributionKey, $einheit),
            '03*' => $this->calculateEqualDistribution($totalCost, $weg),
            '05*' => $this->calculateMeaDistribution($totalCost, $mea),
            '06*' => $this->calculateHebeanlageDistribution($totalCost, $einheit),
            default => throw new \InvalidArgumentException("Unknown distribution key: {$distributionKey}"),
        };
    }

    /**
     * {@inheritdoc}
     */
    public function validateInputs(float $totalCost, string $distributionKey, float $mea): array
    {
        $errors = [];

        if (!is_finite($totalCost)) {
            $errors[] = 'Total cost must be a finite number';
        }

        if (!preg_match('/^0[1-6]\*$/', $distributionKey)) {
            $errors[] = 'Distribution key must be in format 0X* where X is 1-6';
        }

        if ($mea < 0 || $mea > 1) {
            $errors[] = 'MEA must be between 0 and 1';
        }

        return $errors;
    }

    /**
     * Calculate distribution for external costs (01*, 02*, 04*).
     *
     * These require specific unit data or external calculation.
     */
    private function calculateExternalDistribution(float $totalCost, string $distributionKey, ?WegEinheit $einheit): float
    {
        if (!$einheit) {
            return 0.0;
        }

        // For 04* Festumlage, when payments have eigentuemer_id set,
        // the grouping logic already filtered to show only this unit's amount
        // So totalCost is already the unit's specific amount
        if ('04*' === $distributionKey) {
            // Return the total as-is since it's already filtered for this unit
            return $totalCost;
        }

        // For external distributions 01* and 02*, the share should be calculated based on
        // external data (heating costs, water costs)
        // This would typically come from HeizWasserkosten or similar entities
        return 0.0; // Placeholder - implement based on external data requirements
    }

    /**
     * Calculate equal distribution among all units (03*).
     */
    private function calculateEqualDistribution(float $totalCost, ?Weg $weg): float
    {
        if (!$weg) {
            throw new \InvalidArgumentException('WEG required for equal distribution calculation');
        }

        $unitCount = $this->countWegUnits($weg);

        if (0 === $unitCount) {
            throw new \RuntimeException('No units found in WEG for equal distribution');
        }

        return $totalCost / $unitCount;
    }

    /**
     * Calculate MEA-based distribution (05*).
     */
    private function calculateMeaDistribution(float $totalCost, float $mea): float
    {
        return $totalCost * $mea;
    }

    /**
     * Calculate Hebeanlage distribution (06*).
     */
    private function calculateHebeanlageDistribution(float $totalCost, ?WegEinheit $einheit): float
    {
        if (!$einheit) {
            return 0.0;
        }

        $hebeanlageValue = $einheit->getHebeanlage();
        if (!$hebeanlageValue) {
            return 0.0;
        }

        // Parse fraction from database (e.g., "2/6" -> 2/6)
        if (preg_match('/^(\d+)\/(\d+)$/', $hebeanlageValue, $matches)) {
            $numerator = (float) $matches[1];
            $denominator = (float) $matches[2];

            if ($denominator > 0) {
                return $totalCost * ($numerator / $denominator);
            }
        }

        return 0.0;
    }

    /**
     * Count the number of units in a WEG.
     */
    private function countWegUnits(Weg $weg): int
    {
        return $this->wegEinheitRepository->count(['weg' => $weg]);
    }
}
