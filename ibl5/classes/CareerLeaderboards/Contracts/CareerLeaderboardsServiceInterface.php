<?php

declare(strict_types=1);

namespace CareerLeaderboards\Contracts;

/**
 * CareerLeaderboardsServiceInterface - Career Leaderboards business logic
 *
 * Handles data transformation and calculations for career statistics.
 *
 * @phpstan-import-type CareerStatsRow from CareerLeaderboardsRepositoryInterface
 * @phpstan-type FormattedPlayerStats array{pid: int, name: string, games: string|float, minutes: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
 */
interface CareerLeaderboardsServiceInterface
{
    /**
     * Process a player row from database into formatted statistics
     *
     * Transforms raw database row into formatted statistics array,
     * with formatting varying based on whether table contains totals or averages.
     *
     * @param CareerStatsRow $row Database row from statistics table
     * @param string $tableType 'totals' or 'averages' to determine formatting
     * @return FormattedPlayerStats Formatted player statistics
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
     * @return array<string, string> Associative array [table_name => display_label]
     */
    public function getBoardTypes(): array;

    /**
     * Get map of sort categories to column names
     *
     * Returns mapping of database columns to display labels for sort dropdown.
     *
     * @return array<string, string> Associative array [column_name => display_label]
     *
     * **Note:** Percentage columns (fgpct, ftpct, tpct) only work correctly
     * with average tables, not totals tables.
     */
    public function getSortCategories(): array;
}
