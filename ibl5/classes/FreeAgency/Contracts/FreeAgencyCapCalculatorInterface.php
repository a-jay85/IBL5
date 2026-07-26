<?php

declare(strict_types=1);

namespace FreeAgency\Contracts;

/**
 * Interface for the Free Agency cap calculator.
 *
 * @phpstan-import-type CapMetrics from \FreeAgency\FreeAgencyView
 */
interface FreeAgencyCapCalculatorInterface
{
    /**
     * @return array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
     */
    public function calculateTeamCapMetrics(?int $excludeOfferPid = null): array;
}
