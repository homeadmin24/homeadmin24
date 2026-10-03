<?php

declare(strict_types=1);

namespace App\Service\Hga;

use App\Entity\Weg;
use App\Entity\WegEinheit;

/**
 * Core interface for HGA calculations.
 *
 * This interface defines the contract for all HGA calculation services,
 * ensuring consistent API across different calculation types.
 */
interface CalculationInterface
{
    /**
     * Calculate owner's share for a specific cost category.
     *
     * @param float           $totalCost       The total cost amount
     * @param string          $distributionKey The distribution key (01*, 02*, etc.)
     * @param float           $mea             Miteigentumsanteil (ownership share)
     * @param WegEinheit|null $einheit         The unit for special calculations
     * @param Weg|null        $weg             The WEG for context
     *
     * @return float The calculated owner's share
     */
    public function calculateOwnerShare(
        float $totalCost,
        string $distributionKey,
        float $mea,
        ?WegEinheit $einheit = null,
        ?Weg $weg = null,
    ): float;

    /**
     * Validate calculation inputs.
     *
     * @return array<string> Array of validation errors (empty if valid)
     */
    public function validateInputs(float $totalCost, string $distributionKey, float $mea): array;
}
