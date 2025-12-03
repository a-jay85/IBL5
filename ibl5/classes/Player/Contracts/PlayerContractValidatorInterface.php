<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerContractValidatorInterface - Contract for player contract eligibility rules
 * 
 * Defines the interface for validating contract-related eligibility.
 * Encapsulates all CBA rules and contract constraints.
 */
interface PlayerContractValidatorInterface
{
    /**
     * Check if a player can renegotiate their contract
     * 
     * A player can renegotiate if:
     * - They're in their final contract year (contractCurrentYear >= 6), OR
     * - The next year has no salary (eligible for renegotiation), AND
     * - They were NOT rookie optioned in the current year
     * 
     * Players who were rookie optioned cannot renegotiate during the rookie option year.
     * First round picks can't renegotiate in year 4 (rookie option year).
     * Second round picks can't renegotiate in year 3 (rookie option year).
     * 
     * @param PlayerData $playerData The player to check
     * @return bool True if player can renegotiate
     */
    public function canRenegotiateContract(PlayerData $playerData): bool;

    /**
     * Check if a player is eligible for rookie option
     * 
     * Only first and second round draft picks are eligible.
     * Player must have ≤3 years of experience.
     * Eligibility depends on draft round and season phase:
     * 
     * Round 1:
     * - Free Agency phase: Requires 2 years experience, cy4 must be empty
     * - Preseason/HEAT: Requires 3 years experience, cy4 must be empty
     * 
     * Round 2:
     * - Free Agency phase: Requires 1 year experience, cy3 must be empty
     * - Preseason/HEAT: Requires 2 years experience, cy3 must be empty
     * 
     * @param PlayerData $playerData The player to check
     * @param string $seasonPhase Current season phase ("Free Agency", "Preseason", "HEAT", etc.)
     * @return bool True if player is eligible for rookie option
     */
    public function canRookieOption(PlayerData $playerData, string $seasonPhase): bool;

    /**
     * Get the final year rookie contract salary
     * 
     * First round picks have 3-year rookie contracts (final year is cy3).
     * Second round picks have 2-year rookie contracts (final year is cy2).
     * Non-draft picks have no final year rookie contract and return 0.
     * 
     * This is used to determine if a team can exercise the rookie option.
     * 
     * @param PlayerData $playerData The player to check
     * @return int Final year salary amount, or 0 if not a draft pick
     */
    public function getFinalYearRookieContractSalary(PlayerData $playerData): int;

    /**
     * Check if player becomes a free agent in the specified season
     * 
     * A player becomes a free agent when their contract expires.
     * Free agency year is calculated as:
     * draftYear + yearsOfExperience + contractTotalYears - contractCurrentYear
     * 
     * If this calculated year equals the season's ending year, the player becomes a free agent.
     * 
     * @param PlayerData $playerData The player to check
     * @param \Season $season Season object with endingYear property
     * @return bool True if player becomes free agent this season
     */
    public function isPlayerFreeAgent(PlayerData $playerData, \Season $season): bool;

    /**
     * Check if a player's rookie option was previously exercised
     * 
     * The rookie option doubles the salary from the previous year.
     * For first round picks: Check in year 4 if cy4 = cy3 * 2
     * For second round picks: Check in year 3 if cy3 = cy2 * 2
     * 
     * Only returns true when the player is in the year AFTER the rookie option year.
     * So this check only works when:
     * - First round player is in year 4 with cy4 = cy3 * 2
     * - Second round player is in year 3 with cy3 = cy2 * 2
     * 
     * @param PlayerData $playerData The player to check
     * @return bool True if rookie option was exercised
     */
    public function wasRookieOptioned(PlayerData $playerData): bool;
}
