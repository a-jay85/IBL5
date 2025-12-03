<?php

namespace Waivers\Contracts;

use Player\Player;
use Season;

/**
 * WaiversProcessorInterface - Contract for waiver wire business logic
 * 
 * Defines the business logic operations for waiver wire transactions. Handles
 * salary calculations, contract determination, and timing calculations for
 * the waiver claiming process.
 * 
 * @package Waivers\Contracts
 */
interface WaiversProcessorInterface
{
    /**
     * Calculates veteran minimum salary based on years of experience
     * 
     * Determines the minimum salary a player must be signed for based on
     * their NBA experience. Used for waiver claims of players without
     * existing contracts.
     * 
     * @param int $experience Years of NBA experience (0+)
     * @return int Veteran minimum salary in thousands (e.g., 103 = $103,000)
     * 
     * **Salary Tiers (approximate):**
     * - 0-1 years: ~$103k (league minimum)
     * - 2-3 years: ~$103k-$120k
     * - 4+ years: Progressive increases based on experience
     * 
     * **Behaviors:**
     * - Delegates to ContractRules::getVeteranMinimumSalary() for consistency
     * - Returns same values as Free Agency veteran minimum calculations
     */
    public function calculateVeteranMinimumSalary(int $experience): int;

    /**
     * Gets the display contract string for a player
     * 
     * Formats the player's contract for display in the waiver wire UI.
     * Shows remaining years and salaries, or veteran minimum if no contract.
     * 
     * @param Player $player Player object with contract data
     * @param Season $season Season object for phase determination
     * @return string Formatted contract display (e.g., "500 450 400" or "103")
     * 
     * **Format:**
     * - Players with contracts: Space-separated remaining year salaries
     * - Players without contracts: Single veteran minimum value
     * 
     * **Season Phase Handling:**
     * - During "Free Agency" phase: Uses next season's salary
     * - During other phases: Uses current season's salary
     * 
     * **Behaviors:**
     * - Returns veteran minimum as string if no contract exists
     * - Experience is adjusted +1 during Free Agency phase
     */
    public function getPlayerContractDisplay(Player $player, Season $season): string;

    /**
     * Calculates wait time until a player clears waivers
     * 
     * Determines the remaining time before a player on waivers can be claimed
     * without priority order. The standard waiver period is 24 hours.
     * 
     * @param int $dropTime Unix timestamp when player was dropped to waivers
     * @param int $currentTime Current Unix timestamp
     * @return string Formatted wait time (e.g., "(Clears in 5 h, 30 m, 15 s)") or empty string if cleared
     * 
     * **Behaviors:**
     * - Returns empty string if 24+ hours have passed (player has cleared)
     * - Returns formatted countdown string if still in waiver period
     * - Uses h/m/s format for readability
     * 
     * **Constants:**
     * - Waiver period: 86400 seconds (24 hours)
     */
    public function getWaiverWaitTime(int $dropTime, int $currentTime): string;

    /**
     * Determines contract data for a waiver wire signing
     * 
     * Analyzes a player's existing contract situation to determine what
     * contract terms apply when signing them from waivers.
     * 
     * @param array $playerData Player data array with keys:
     *   - 'cy': int - Current contract year (0-based)
     *   - 'cyt': int - Total contract years
     *   - 'cy1'-'cy6': int - Salary for each contract year
     *   - 'exp': int - Years of experience
     * @param Season $season Season object for phase determination
     * @return array Contract determination result:
     *   - 'hasExistingContract': bool - Whether player has remaining contract
     *   - 'salary': int - Salary amount (existing or calculated vet min)
     * 
     * **Season Phase Handling:**
     * - During "Free Agency" phase: Checks next season's salary, exp+1
     * - During other phases: Checks current season's salary, current exp
     * 
     * **Contract Logic:**
     * - If player has salary > 0: hasExistingContract = true, return that salary
     * - If no salary: hasExistingContract = false, return veteran minimum
     */
    public function determineContractData(array $playerData, Season $season): array;
}
