<?php

declare(strict_types=1);

namespace CareerLeaderboards\Contracts;

/**
 * CareerLeaderboardsServiceInterface - Career Leaderboards business logic
 *
 * Handles data transformation and calculations for career statistics.
 *
 * @phpstan-import-type CareerStatsRow from CareerLeaderboardsRepositoryInterface
 * @phpstan-type FormattedPlayerStats array{pid: int, name: string, games: string|float, minutes: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, drb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
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
     * Phase select options
     *
     * @return array<string, string> [phase_key => display_label]
     */
    public function getPhases(): array;

    /**
     * Whether the phase has an averages table (rookie and sophomore do not)
     */
    public function phaseHasAverages(string $phase): bool;

    /**
     * Allowlist a phase key; unknown values become 'regular'
     */
    public function resolvePhase(string $phase): string;

    /**
     * Allowlist a mode; unknown values and averages on a phase without averages become 'totals'
     *
     * @return 'totals'|'averages'
     */
    public function resolveMode(string $phase, string $mode): string;

    /**
     * Resolve phase + mode to a repository table key
     *
     * Unknown phase falls back to regular season, unknown mode or averages
     * on a phase without an averages table falls back to totals.
     */
    public function resolveTableKey(string $phase, string $mode): string;

    /**
     * Sort By options for the given mode (PPG reads "PTS" on totals)
     *
     * @return array<string, string> [sort_key => display_label], in Season-tab order minus QA
     */
    public function getSortOptions(string $mode): array;

    /**
     * Whether a sort key is a known option (every key works in both modes)
     */
    public function isSortAvailable(string $key, string $mode): bool;

    /**
     * Allowlist a sort key for the mode; unknown or unavailable keys become 'PPG'
     */
    public function resolveSortKey(string $key, string $mode): string;

    /**
     * Resolve a sort key to a repository sort column (see VALID_SORT_COLUMNS)
     */
    public function resolveSortColumn(string $key, string $mode): string;
}
