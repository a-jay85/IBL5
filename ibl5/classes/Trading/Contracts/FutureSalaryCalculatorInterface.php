<?php

declare(strict_types=1);

namespace Trading\Contracts;

use Season\Season;

/**
 * FutureSalaryCalculatorInterface - Contract for future salary computation
 */
interface FutureSalaryCalculatorInterface
{
    /**
     * Calculate future salary commitments for a set of players
     *
     * Extracts the salary calculation logic that was previously in UIHelper.
     * Computes per-year salary totals and player counts for up to 6 future years.
     *
     * @param list<array<string, mixed>> $players Array of player rows from repository
     * @param Season $season Season object for phase-based contract year adjustment
     * @return array{player: array<int, int>, hold: array<int, int>} Future salary data:
     *         - 'player': Salary totals by future year index (0-5)
     *         - 'hold': Player counts with salary by future year index (0-5)
     * @see FutureSalaryCalculatorInterface::calculateFutureSalaries()
     */
    public function calculateFutureSalaries(array $players, Season $season): array;
}
