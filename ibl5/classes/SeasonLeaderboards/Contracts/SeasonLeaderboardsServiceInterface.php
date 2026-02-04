<?php

declare(strict_types=1);

namespace SeasonLeaderboards\Contracts;

/**
 * SeasonLeaderboardsServiceInterface - Season leaders business logic
 *
 * Handles data transformation and calculations for season statistics.
 *
 * @phpstan-import-type HistRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-type ProcessedStats array{pid: int, name: string, year: int, teamname: string, teamid: int, team_city: string, color1: string, color2: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, points: int, fgp: string, ftp: string, tgp: string, mpg: string, fgmpg: string, fgapg: string, ftmpg: string, ftapg: string, tgmpg: string, tgapg: string, orbpg: string, rpg: string, apg: string, spg: string, tpg: string, bpg: string, fpg: string, ppg: string, qa: string}
 */
interface SeasonLeaderboardsServiceInterface
{
    /**
     * Process a player row from database into formatted statistics
     *
     * Transforms raw database row into formatted statistics array with
     * calculated values, percentages, and per-game averages.
     *
     * @param HistRow $row Database row from ibl_hist table
     * @return ProcessedStats Formatted player statistics
     *
     * **QA Formula:**
     * (pts + reb + 2*ast + 2*stl + 2*blk - (fga-fgm) - (fta-ftm) - tvr - pf) / games
     *
     * **Behaviors:**
     * - Uses StatsFormatter for consistent formatting
     * - Handles division by zero (0 games returns "0.0" for averages)
     * - Points calculated as (2*fgm + ftm + tgm)
     */
    public function processPlayerRow(array $row): array;

    /**
     * Get sort option labels for display
     *
     * Returns array of human-readable labels for sort dropdown options.
     *
     * @return list<string> Array of 20 sort option labels
     */
    public function getSortOptions(): array;
}
