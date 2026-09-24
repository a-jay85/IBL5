<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntryRepositoryInterface - Contract for depth chart data access
 *
 * Defines database operations for reading and updating depth chart data,
 * including player positions, team history, and submission tracking.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 *
 * @phpstan-type DepthChartValues array{pg: int, sg: int, sf: int, pf: int, c: int, canPlayInGame: int, min: int, of: int, df: int, oi: int, di: int, bh: int, ...<string, mixed>}
 */
interface DepthChartEntryRepositoryInterface
{
    /**
     * Get all active players on a team with depth chart data
     *
     * Returns database result set containing all players on the team who:
     * - Are not retired (retired = 0)
     * - Are on the active roster (ordinal <= WAIVERS_ORDINAL)
     * - Sorted by ordinal (draft order)
     *
     * @param int $teamid Team ID for database query
     * @return list<PlayerRow>
     *
     * **Important Behaviors:**
     * - Filters out retired players automatically
     * - Results include all depth chart fields (dc_pg_depth, dc_sg_depth, etc.)
     * - Ordered by player ordinal for consistent display
     * - Team ID is cast to int for safety
     */
    public function getPlayersOnTeam(int $teamid);

    /**
     * Single prepared UPDATE ibl_plr keyed by pid AND teamid.
     *
     * @param int $pid Player id (WHERE clause key)
     * @param int $teamid Session-derived team; a pid on another team matches no row
     * @param DepthChartValues $depthChartValues
     * @return bool True when the statement executed (0 affected rows still returns true)
     */
    public function updatePlayerDepthChart(int $pid, int $teamid, array $depthChartValues): bool;

    /**
     * Update team timestamps for depth chart submissions
     *
     * Records when a team last submitted their depth chart by updating
     * both regular season and simulation-mode timestamps in ibl_team_info.
     *
     * @param string $teamName Team name (used to identify team)
     * @return bool True if update succeeded, false if update failed
     *
     * **Updated Fields:**
     * - ibl_team_info.depth: Updated to NOW() (current timestamp)
     * - ibl_team_info.sim_depth: Updated to NOW() (current timestamp)
     *
     * **Important Behaviors:**
     * - Team name is sanitized via DatabaseService::escapeString() for security
     * - Updates the current NOW() timestamp (MySQL server time)
     * - Returns false if update fails (transactional check)
     * - Both fields are updated for consistency (regular season + simulation)
     */
    public function updateTeamHistory(string $teamName): bool;
}
