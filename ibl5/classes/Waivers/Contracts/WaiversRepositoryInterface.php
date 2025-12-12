<?php

declare(strict_types=1);

namespace Waivers\Contracts;

/**
 * WaiversRepositoryInterface - Contract for waiver wire database operations
 * 
 * Defines the data access layer for waiver wire transactions. Handles all
 * database operations related to dropping players to waivers and signing
 * players from the waiver pool.
 * 
 * @package Waivers\Contracts
 */
interface WaiversRepositoryInterface
{
    /**
     * Drops a player to waivers
     * 
     * Updates the player's record to place them on waivers. Sets ordinal to 1000
     * (waiver pool indicator) and records the drop timestamp for waiver claim
     * waiting period calculation.
     * 
     * @param int $playerID Player ID to drop (must be positive integer)
     * @param int $timestamp Current Unix timestamp for drop time tracking
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Sets `ordinal` = 1000 (waiver pool status)
     * - Sets `droptime` = provided timestamp
     * - Updates ibl_plr table
     * 
     * **Behaviors:**
     * - Uses LIMIT 1 for safety
     * - Does not validate player ownership (caller responsibility)
     * - Does not send notifications (caller responsibility)
     */
    public function dropPlayerToWaivers(int $playerID, int $timestamp): bool;

    /**
     * Signs a player from waivers
     * 
     * Updates the player's record to assign them to a team. Handles both players
     * with existing contracts and free agents who need veteran minimum contracts.
     * 
     * @param int $playerID Player ID to sign (must be positive integer)
     * @param array $team Team data array with keys:
     *   - 'team_name': string - Full team name
     *   - 'teamid': int - Team ID for foreign key relationship
     * @param array $contractData Contract information with keys:
     *   - 'hasExistingContract': bool - Whether player has existing contract
     *   - 'salary': int - Salary amount (used only if no existing contract)
     * @return bool True if update succeeded, false on database error
     * 
     * **Database Changes:**
     * - Sets `ordinal` = 800 (bench player status)
     * - Sets `bird` = 0 (resets Bird rights on waiver claim)
     * - Sets `teamname` and `tid` to new team
     * - Sets `droptime` = 0 (clears waiver status)
     * - If no existing contract: sets cy=0, cyt=1, cy1=salary, cy2-6=0
     * 
     * **Behaviors:**
     * - Escapes team name for SQL safety
     * - Uses LIMIT 1 for safety
     * - Does not validate roster space (caller responsibility)
     */
    public function signPlayerFromWaivers(int $playerID, array $team, array $contractData): bool;
}
