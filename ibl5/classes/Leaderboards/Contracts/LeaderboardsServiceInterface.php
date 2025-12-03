<?php

declare(strict_types=1);

namespace Leaderboards\Contracts;

/**
 * LeaderboardsServiceInterface - Leaderboards business logic
 *
 * Handles data transformation and calculations for career statistics.
 */
interface LeaderboardsServiceInterface
{
    /**
     * Process a player row from database into formatted statistics
     *
     * Transforms raw database row into formatted statistics array,
     * with formatting varying based on whether table contains totals or averages.
     *
     * @param array $row Database row from statistics table
     * @param string $tableType 'totals' or 'averages' to determine formatting
     * @return array Formatted player statistics with keys:
     *               - pid (int): Player ID
     *               - name (string): Player name with '*' suffix if retired
     *               - games (string|int): Formatted game count
     *               - minutes, fgm, fga, ftm, fta, tgm, tga (string): Formatted stats
     *               - fgp, ftp, tgp (string): Formatted percentages
     *               - orb, reb, ast, stl, tvr, blk, pf, pts (string): Formatted stats
     *
     * **Totals Formatting:**
     * - Uses StatsFormatter::formatTotal() for comma-separated integers
     * - Percentages calculated from made/attempted
     *
     * **Averages Formatting:**
     * - Uses StatsFormatter::formatAverage() for 2 decimal places
     * - Percentages read from pre-calculated columns (fgpct, ftpct, tpct)
     *
     * **Behaviors:**
     * - Appends '*' to retired player names
     * - Uses StatsFormatter for consistent number formatting
     */
    public function processPlayerRow(array $row, string $tableType): array;

    /**
     * Get map of board types to table names
     *
     * Returns mapping of database table names to display labels.
     *
     * @return array Associative array [table_name => display_label]:
     *               - 'ibl_hist' => 'Regular Season Totals'
     *               - 'ibl_season_career_avgs' => 'Regular Season Averages'
     *               - 'ibl_playoff_career_totals' => 'Playoff Totals'
     *               - 'ibl_playoff_career_avgs' => 'Playoff Averages'
     *               - 'ibl_heat_career_totals' => 'H.E.A.T. Totals'
     *               - 'ibl_heat_career_avgs' => 'H.E.A.T. Averages'
     *               - 'ibl_olympics_career_totals' => 'Olympic Totals'
     *               - 'ibl_olympics_career_avgs' => 'Olympic Averages'
     */
    public function getBoardTypes(): array;

    /**
     * Get map of sort categories to column names
     *
     * Returns mapping of database columns to display labels for sort dropdown.
     *
     * @return array Associative array [column_name => display_label]:
     *               - 'pts' => 'Points'
     *               - 'games' => 'Games'
     *               - 'minutes' => 'Minutes'
     *               - etc. (19 total sort options)
     *
     * **Note:** Percentage columns (fgpct, ftpct, tpct) only work correctly
     * with average tables, not totals tables.
     */
    public function getSortCategories(): array;
}
