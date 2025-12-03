<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerInterface - Contract for the Player facade
 * 
 * Defines the public interface for player-related operations.
 * This facade delegates to specialized calculator and validator classes.
 */
interface PlayerInterface
{
    /**
     * Decorate player name with status indicators
     * 
     * Applies visual indicators to the player name:
     * - "(name)*" for players on waivers
     * - "name^" for players eligible for free agency at end of season
     * - "name" for all others
     * 
     * @return string Decorated player name with indicators
     */
    public function decoratePlayerName();

    /**
     * Get the current season salary
     * 
     * Returns the salary for the player's current contract year.
     * Returns 0 if the player has no salary in the current year.
     * 
     * @return int Current season salary in dollars
     */
    public function getCurrentSeasonSalary();

    /**
     * Get the player's free agency demands
     * 
     * Returns an array of base salary demands for each contract year (1-6).
     * These values are before any team/player modifiers are applied.
     * 
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int} Base demands by year
     */
    public function getFreeAgencyDemands();

    /**
     * Calculate when an injured player will return
     * 
     * If daysRemainingForInjury is 0 or negative, returns empty string.
     * Otherwise adds the days to the last simulation end date and returns the date.
     * 
     * @param string $rawLastSimEndDate The date from the last simulation (Y-m-d format)
     * @return string Return date in Y-m-d format, or empty string if not injured
     */
    public function getInjuryReturnDate($rawLastSimEndDate);

    /**
     * Get the next season's salary
     * 
     * Returns the salary for the player's next contract year.
     * Returns 0 if the player has no salary in the next year.
     * 
     * @return int Next season salary in dollars
     */
    public function getNextSeasonSalary();

    /**
     * Get long buyout terms (6 years)
     * 
     * Calculates the remaining contract salary spread evenly over 6 years.
     * Used when a team buys out a player for long-term cap relief.
     * 
     * @return array<int> Buyout amounts for each year (indices 1-6)
     */
    public function getLongBuyoutArray();

    /**
     * Get short buyout terms (2 years)
     * 
     * Calculates the remaining contract salary spread evenly over 2 years.
     * Used when a team buys out a player for immediate cap relief.
     * 
     * @return array<int> Buyout amounts for each year (indices 1-2)
     */
    public function getShortBuyoutArray();

    /**
     * Get remaining contract years and salaries
     * 
     * Returns an array keyed by remaining contract year number (1, 2, 3, etc.)
     * with salary values. Only includes years with non-zero salaries.
     * Always includes year 1 even if salary is 0.
     * 
     * @return array<int, int> Remaining years with salaries
     */
    public function getRemainingContractArray();

    /**
     * Get total remaining salary on the contract
     * 
     * Sums all remaining contract salary from the current year through contract end.
     * Used for cap management and buyout calculations.
     * 
     * @return int Total remaining salary in dollars
     */
    public function getTotalRemainingSalary();

    /**
     * Get future salaries for the next 6 contract years
     * 
     * Returns an array of 6 elements representing the salary for each contract year.
     * Starts from the current contract year and pads with zeros if needed.
     * This ensures a consistent 6-element array regardless of years remaining.
     * 
     * @return array<int> Future salaries for years 1-6
     */
    public function getFutureSalaries(): array;

    /**
     * Check if player can renegotiate contract
     * 
     * A player can renegotiate if:
     * - They're in their final contract year, OR
     * - The next year has no salary (gap year), AND
     * - They were NOT rookie optioned in the current year
     * 
     * @return bool True if player can renegotiate
     */
    public function canRenegotiateContract();

    /**
     * Check if player is eligible for rookie option
     * 
     * Only first and second round picks with ≤3 years experience are eligible.
     * Eligibility varies by season phase and years of experience:
     * - Round 1 FA: 2 years experience, cy4 empty
     * - Round 1 Preseason/HEAT: 3 years experience, cy4 empty
     * - Round 2 FA: 1 year experience, cy3 empty
     * - Round 2 Preseason/HEAT: 2 years experience, cy3 empty
     * 
     * @param string $seasonPhase Current season phase ("Free Agency", "Preseason", "HEAT", etc.)
     * @return bool True if player is eligible for rookie option
     */
    public function canRookieOption($seasonPhase);

    /**
     * Get the final year rookie contract salary
     * 
     * For first round picks, returns cy3 (final year of 3-year rookie contract).
     * For second round picks, returns cy2 (final year of 2-year rookie contract).
     * For non-draft picks, returns 0.
     * 
     * @return int Final year rookie contract salary, or 0 if not a draft pick
     */
    public function getFinalYearRookieContractSalary();

    /**
     * Check if player becomes a free agent in the specified season
     * 
     * Calculates: draftYear + yearsOfExperience + contractTotalYears - contractCurrentYear
     * If this equals the season's ending year, the player becomes a free agent.
     * 
     * @param int|\Season $season Season object or ending year to check
     * @return bool True if player becomes free agent in this season
     */
    public function isPlayerFreeAgent($season);

    /**
     * Check if a player's rookie option was previously exercised
     * 
     * First round: Check if year 4 salary is double year 3 salary.
     * Second round: Check if year 3 salary is double year 2 salary.
     * Only returns true when in the year AFTER the rookie option year.
     * 
     * @return bool True if rookie option was exercised
     */
    public function wasRookieOptioned();
}
