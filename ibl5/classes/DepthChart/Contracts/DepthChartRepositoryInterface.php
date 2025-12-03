<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartRepositoryInterface - Contract for depth chart data access
 * 
 * Defines database operations for reading and updating depth chart data,
 * including player positions, team history, and submission tracking.
 */
interface DepthChartRepositoryInterface
{
    /**
     * Get all active players on a team with depth chart data
     * 
     * Returns database result set containing all players on the team who:
     * - Are not retired (retired = 0)
     * - Are on the active roster (ordinal <= WAIVERS_ORDINAL)
     * - Sorted by ordinal (draft order)
     * 
     * @param string $teamName Team name for lookup
     * @param int $teamID Team ID for database query
     * @return mixed Database result resource (varies by database abstraction layer)
     * 
     * **Important Behaviors:**
     * - Filters out retired players automatically
     * - Results include all depth chart fields (dc_PGDepth, dc_SGDepth, etc.)
     * - Ordered by player ordinal for consistent display
     * - Uses DatabaseService::escapeString() for team name to prevent SQL injection
     * - Team ID is cast to int for safety
     */
    public function getPlayersOnTeam(string $teamName, int $teamID);

    /**
     * Update a player's depth chart configuration across all positions
     * 
     * Updates all depth chart-related fields for a single player by name.
     * Performs bulk update with 12 separate UPDATE statements, one per field.
     * Returns success/failure status for all updates combined.
     * 
     * @param string $playerName Player name (used as lookup key in WHERE clause)
     * @param array $depthChartValues Validated depth chart values with these keys:
     *                                - pg: Position depth for PG (0-5)
     *                                - sg: Position depth for SG (0-5)
     *                                - sf: Position depth for SF (0-5)
     *                                - pf: Position depth for PF (0-5)
     *                                - c: Position depth for C (0-5)
     *                                - active: Active status (0 or 1)
     *                                - min: Projected minutes (0-40)
     *                                - of: Offensive focus (0-3: Auto/Outside/Drive/Post)
     *                                - df: Defensive focus (0-3: Auto/Outside/Drive/Post)
     *                                - oi: Offensive intensity (-2 to 2)
     *                                - di: Defensive intensity (-2 to 2)
     *                                - bh: Ball handling (-2 to 2)
     * 
     * @return bool True if all 12 updates succeeded, false if any update failed
     * 
     * **Important Behaviors:**
     * - Sanitizes player name via DatabaseService::escapeString() to prevent SQL injection
     * - All numeric values converted to int before query construction
     * - Returns false immediately on first query failure (transactional integrity)
     * - Uses single-step string escaping (legacy mode - compatible with php-nuke db layer)
     */
    public function updatePlayerDepthChart(string $playerName, array $depthChartValues): bool;

    /**
     * Update team history timestamps for depth chart submissions
     * 
     * Records when a team last submitted their depth chart by updating
     * both regular season and simulation-mode timestamps in team history.
     * 
     * @param string $teamName Team name (used to identify team in history table)
     * @return bool True if both updates succeeded, false if either update failed
     * 
     * **Updated Fields:**
     * - ibl_team_history.depth: Updated to NOW() (current timestamp)
     * - ibl_team_history.sim_depth: Updated to NOW() (current timestamp)
     * 
     * **Important Behaviors:**
     * - Team name is sanitized via DatabaseService::escapeString() for security
     * - Updates the current NOW() timestamp (MySQL server time)
     * - Returns false if any update fails (transactional check)
     * - Both fields are updated for consistency (regular season + simulation)
     */
    public function updateTeamHistory(string $teamName): bool;
}
