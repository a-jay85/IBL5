<?php

declare(strict_types=1);

namespace Trading;

use Season\Season;
use Trading\Contracts\FutureSalaryCalculatorInterface;

/**
 * Stateless calculator for future salary commitments.
 *
 * @see FutureSalaryCalculatorInterface
 */
final class FutureSalaryCalculator implements FutureSalaryCalculatorInterface
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
    public function calculateFutureSalaries(array $players, Season $season): array
    {
        $futureSalary = [
            'player' => [0, 0, 0, 0, 0, 0],
            'hold' => [0, 0, 0, 0, 0, 0],
        ];

        foreach ($players as $playerRow) {
            $contractYearRaw = $playerRow['cy'] ?? 0;
            $contractYear = is_int($contractYearRaw) ? $contractYearRaw : 0;

            // Adjust contract year based on season phase
            if ($season->advancesContractYears()) {
                $contractYear++;
            }
            if ($contractYear === 0) {
                $contractYear = 1;
            }

            // Calculate future salary commitments
            $i = 0;
            $cy = $contractYear;
            while ($cy < 7) {
                $cyRawValue = $playerRow["salary_yr{$cy}"] ?? 0;
                $cyValue = is_int($cyRawValue) ? $cyRawValue : 0;
                $futureSalary['player'][$i] += $cyValue;
                if ($cyValue > 0) {
                    $futureSalary['hold'][$i]++;
                }
                $cy++;
                $i++;
            }
        }

        return $futureSalary;
    }
}
