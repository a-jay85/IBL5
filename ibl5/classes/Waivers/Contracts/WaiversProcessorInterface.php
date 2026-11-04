<?php

declare(strict_types=1);

namespace Waivers\Contracts;

use Player\Player;
use Season\Season;

/**
 * WaiversProcessorInterface - Contract for waiver wire business logic
 *
 * Defines the business logic operations for waiver wire transactions. Handles
 * salary calculations, contract determination, and timing calculations for
 * the waiver claiming process.
 *
 * @package Waivers\Contracts
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type WaiverAudit array{event: string, context: array<string, mixed>}
 * @phpstan-type WaiverNotification array{mailSubject: ?string, message: string, audit: WaiverAudit}
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
     * Determines contract data for a waiver wire signing
     * 
     * Analyzes a player's existing contract situation to determine what
     * contract terms apply when signing them from waivers.
     * 
     * @param PlayerRow $playerData Player data array with contract fields (cy, cyt, salary_yr1-salary_yr6, exp)
     * @param Season $season Season object for phase determination
     * @return array{hasExistingContract: bool, salary: int} Contract determination result:
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

    /**
     * Process dropping a player to waivers.
     *
     * Validates, writes to DB, and creates news story. Sends and audits nothing: on success the
     * result carries a 'notification' for finishCommittedMove(), which the caller runs after commit.
     *
     * @return array{success: bool, result?: string, error?: string, notification?: WaiverNotification}
     */
    public function processDrop(?int $playerID, string $teamName, int $rosterSlots, int $totalSalary): array;

    /**
     * Process signing a player from waivers.
     *
     * Validates, determines contract, writes to DB, and creates news story.
     * Sends and audits nothing: on success the result carries a 'notification' for
     * finishCommittedMove(), which the caller runs after commit.
     *
     * @param int $openRosterSlots Open roster spots counting injured players (15 minus every rostered player)
     * @param int $healthyRosterSlots Healthy roster slots available; drives only the 12-healthy hard-cap rule
     * @return array{success: bool, result?: string, error?: string, notification?: WaiverNotification}
     */
    public function processAdd(?int $playerID, string $teamName, int $openRosterSlots, int $healthyRosterSlots, int $totalSalary): array;

    /**
     * Write the audit entry, then send the email (when mailSubject is set) and the Discord post
     * for a committed move.
     *
     * Call only after the transaction holding the write has committed. Never throws: a failed
     * audit write, a failed send (thrown or reported by a false return), and a failed warning
     * write never stop the later steps or reach the caller.
     *
     * @param WaiverNotification $notification
     */
    public function finishCommittedMove(array $notification): void;
}
