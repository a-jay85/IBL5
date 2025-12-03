<?php

declare(strict_types=1);

namespace Leaderboards\Contracts;

/**
 * LeaderboardsRepositoryInterface - Leaderboards database operations
 *
 * Handles all database operations for career statistics across
 * multiple table types (regular season, playoffs, H.E.A.T., Olympics).
 */
interface LeaderboardsRepositoryInterface
{
    /**
     * Get leaderboard data based on filters
     *
     * Retrieves career statistics from specified table with optional
     * filtering and sorting.
     *
     * @param string $tableKey Table name (must be in whitelist)
     * @param string $sortColumn Column name to sort by (must be in whitelist)
     * @param int $activeOnly 1 to exclude retired players, 0 to include all
     * @param int $limit Maximum records to return (0 for unlimited)
     * @return array Result with keys:
     *               - 'result' (resource): Database query result
     *               - 'count' (int): Number of rows returned
     *
     * **Valid Tables:**
     * - ibl_hist (aggregated by player for regular season totals)
     * - ibl_season_career_avgs
     * - ibl_playoff_career_totals
     * - ibl_playoff_career_avgs
     * - ibl_heat_career_totals
     * - ibl_heat_career_avgs
     * - ibl_olympics_career_totals
     * - ibl_olympics_career_avgs
     *
     * **Valid Sort Columns:**
     * pts, games, minutes, fgm, fga, fgpct, ftm, fta, ftpct,
     * tgm, tga, tpct, orb, reb, ast, stl, tvr, blk, pf
     *
     * **Behaviors:**
     * - Throws InvalidArgumentException for invalid table/column
     * - ibl_hist uses GROUP BY pid with SUM aggregation
     * - Other tables join with ibl_plr for retired status
     * - Results sorted DESC by specified column
     * - Filters out records with games = 0
     *
     * @throws \InvalidArgumentException If table or column not in whitelist
     */
    public function getLeaderboards(
        string $tableKey,
        string $sortColumn,
        int $activeOnly,
        int $limit
    ): array;

    /**
     * Check if a table contains totals or averages
     *
     * Determines whether the specified table contains aggregate totals
     * or per-game averages.
     *
     * @param string $tableKey Table name
     * @return string 'totals' or 'averages'
     *
     * **Average Tables:**
     * - ibl_season_career_avgs
     * - ibl_playoff_career_avgs
     * - ibl_heat_career_avgs
     * - ibl_olympics_career_avgs
     *
     * **Total Tables:**
     * All other tables including ibl_hist
     */
    public function getTableType(string $tableKey): string;
}
