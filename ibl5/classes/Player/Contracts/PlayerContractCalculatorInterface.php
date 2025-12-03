<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerContractCalculatorInterface - Contract for player contract calculations
 * 
 * Defines the interface for all salary and contract-related calculations.
 * Does not perform data persistence; only calculations based on player data.
 */
interface PlayerContractCalculatorInterface
{
    /**
     * Calculate the current season salary
     * 
     * Returns the salary for the player's current contract year.
     * For year 0, defaults to year 1 salary.
     * For year 7+, returns 0 (off the books).
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return int Current season salary in dollars
     */
    public function getCurrentSeasonSalary(PlayerData $playerData): int;

    /**
     * Calculate the next season's salary
     * 
     * Returns the salary for the player's next contract year (currentYear + 1).
     * Returns 0 if the next year is beyond year 6 or has no salary.
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return int Next season salary in dollars
     */
    public function getNextSeasonSalary(PlayerData $playerData): int;

    /**
     * Get future salaries for the next 6 contract years
     * 
     * Returns a 6-element array with the salary for each contract year.
     * Starts from the current contract year and pads with zeros if contract ends before year 6.
     * This ensures the returned array always has exactly 6 elements.
     * 
     * Example: If current year is 3 and remaining salaries are [0, 500000, 600000]:
     * Result: [0, 500000, 600000, 0, 0, 0]
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return array<int> Future salaries padded to 6 elements
     */
    public function getFutureSalaries(PlayerData $playerData): array;

    /**
     * Get an array of remaining contract years and salaries
     * 
     * Returns an array keyed by remaining contract year number (1, 2, 3, etc.)
     * with the corresponding salary values.
     * Only includes years with non-zero salaries EXCEPT year 1 is always included.
     * 
     * Example: Contract years 3-5 with salaries [500000, 0, 600000] returns:
     * [1 => 500000, 3 => 600000]
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return array<int, int> Remaining years with salaries (year => salary)
     */
    public function getRemainingContractArray(PlayerData $playerData): array;

    /**
     * Calculate total remaining salary on the contract
     * 
     * Sums the remaining contract array from current year through contract end.
     * Used for cap management, buyout calculations, and contract analysis.
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return int Total remaining salary in dollars
     */
    public function getTotalRemainingSalary(PlayerData $playerData): int;

    /**
     * Calculate long buyout terms (6 years)
     * 
     * Spreads the total remaining contract salary evenly over 6 years.
     * Used when a team buys out a player to clear cap space over a longer period.
     * Returns an array of 6 equal salary amounts.
     * 
     * Example: Remaining salary $600,000 returns [100000, 100000, 100000, 100000, 100000, 100000]
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return array<int> Buyout amounts for each of 6 years
     */
    public function getLongBuyoutArray(PlayerData $playerData): array;

    /**
     * Calculate short buyout terms (2 years)
     * 
     * Spreads the total remaining contract salary evenly over 2 years.
     * Used when a team buys out a player for immediate cap relief.
     * Returns an array of 2 equal salary amounts.
     * 
     * Example: Remaining salary $600,000 returns [300000, 300000]
     * 
     * @param PlayerData $playerData The player to calculate for
     * @return array<int> Buyout amounts for each of 2 years
     */
    public function getShortBuyoutArray(PlayerData $playerData): array;
}
