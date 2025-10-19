<?php

declare(strict_types=1);

namespace App\Service\Hga\Calculation;

use App\Entity\Weg;
use App\Entity\WegEinheit;
use App\Repository\HeizWasserkostenRepository;

/**
 * External cost calculation service.
 *
 * Handles calculation of externally calculated costs like
 * heating and water costs that come from external billing systems.
 */
class ExternalCostService
{
    public function __construct(
        private HeizWasserkostenRepository $heizWasserkostenRepository,
    ) {
    }

    /**
     * Get heating costs for a unit and year.
     *
     * @return array{
     *   total: float,
     *   unit_share: float,
     *   distribution_key: string
     * }
     */
    public function getHeatingCosts(WegEinheit $einheit, int $year): array
    {
        $cost = $this->heizWasserkostenRepository->findByEinheitAndYear($einheit, $year);

        if (!$cost) {
            return [
                'total' => 0.0,
                'unit_share' => 0.0,
                'distribution_key' => '01*',
            ];
        }

        // Get WEG total for this year
        $wegTotal = $this->heizWasserkostenRepository->findWegGesamtByYear($einheit->getWeg(), $year);
        $totalHeating = $wegTotal ? $wegTotal->getHeizkosten() : 0.0;

        return [
            'total' => (float) $totalHeating,
            'unit_share' => (float) ($cost->getHeizkosten() ?? 0.0),
            'distribution_key' => '01*',
        ];
    }

    /**
     * Get water costs for a unit and year.
     *
     * @return array{
     *   total: float,
     *   unit_share: float,
     *   distribution_key: string
     * }
     */
    public function getWaterCosts(WegEinheit $einheit, int $year): array
    {
        $cost = $this->heizWasserkostenRepository->findByEinheitAndYear($einheit, $year);

        if (!$cost) {
            return [
                'total' => 0.0,
                'unit_share' => 0.0,
                'distribution_key' => '02*',
            ];
        }

        // Get WEG total for this year
        $wegTotal = $this->heizWasserkostenRepository->findWegGesamtByYear($einheit->getWeg(), $year);
        $totalWater = $wegTotal ? $wegTotal->getWasserKosten() : 0.0;

        return [
            'total' => (float) $totalWater,
            'unit_share' => (float) ($cost->getWasserKosten() ?? 0.0),
            'distribution_key' => '02*',
        ];
    }

    /**
     * Get all external costs for a unit and year.
     *
     * @return array{
     *   heating: array{total: float, unit_share: float, distribution_key: string},
     *   water: array{total: float, unit_share: float, distribution_key: string},
     *   total: float,
     *   unit_total: float
     * }
     */
    public function getAllExternalCosts(WegEinheit $einheit, int $year): array
    {
        $heating = $this->getHeatingCosts($einheit, $year);
        $water = $this->getWaterCosts($einheit, $year);

        return [
            'heating' => $heating,
            'water' => $water,
            'total' => $heating['total'] + $water['total'],
            'unit_total' => $heating['unit_share'] + $water['unit_share'],
        ];
    }

    /**
     * Get total external costs for the entire WEG.
     *
     * @return array{
     *   heating_total: float,
     *   water_total: float,
     *   grand_total: float
     * }
     */
    public function getTotalExternalCostsForWeg(Weg $weg, int $year): array
    {
        $units = $weg->getEinheiten();

        $heatingTotal = 0.0;
        $waterTotal = 0.0;

        foreach ($units as $unit) {
            $costs = $this->getAllExternalCosts($unit, $year);
            $heatingTotal += $costs['heating']['unit_share'];
            $waterTotal += $costs['water']['unit_share'];
        }

        return [
            'heating_total' => $heatingTotal,
            'water_total' => $waterTotal,
            'grand_total' => $heatingTotal + $waterTotal,
        ];
    }

    /**
     * Validate external cost data for a WEG and year.
     *
     * @return array<string> Array of validation errors (empty if valid)
     */
    public function validateExternalCostData(Weg $weg, int $year): array
    {
        $errors = [];

        // Check if WEG total data exists
        $wegTotal = $this->heizWasserkostenRepository->findWegGesamtByYear($weg, $year);

        if (!$wegTotal) {
            // External costs are optional - if no data exists, that's fine
            // The system will use 0.0 values
            return [];
        }

        // If WEG total exists, check if unit data exists for all units
        $units = $weg->getEinheiten();
        $unitsWithoutData = [];

        foreach ($units as $unit) {
            $unitCost = $this->heizWasserkostenRepository->findByEinheitAndYear($unit, $year);
            if (!$unitCost) {
                $unitsWithoutData[] = $unit->getNummer();
            }
        }

        if (!empty($unitsWithoutData)) {
            $errors[] = \sprintf('Missing external cost data for units: %s', implode(', ', $unitsWithoutData));
        }

        return $errors;
    }
}
