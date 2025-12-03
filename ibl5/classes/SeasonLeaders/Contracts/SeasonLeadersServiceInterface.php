<?php

namespace SeasonLeaders\Contracts;

/**
 * SeasonLeadersServiceInterface - Season leaders business logic
 *
 * Handles data transformation and calculations for season statistics.
 */
interface SeasonLeadersServiceInterface
{
    /**
     * Process a player row from database into formatted statistics
     *
     * Transforms raw database row into formatted statistics array with
     * calculated values, percentages, and per-game averages.
     *
     * @param array $row Database row from ibl_hist table
     * @return array Formatted player statistics with keys:
     *               - Basic: pid, name, year, teamname, teamid
     *               - Raw: games, minutes, fgm, fga, ftm, fta, tgm, tga, orb, reb, ast, stl, tvr, blk, pf
     *               - Calculated: points
     *               - Percentages (3 decimals): fgp, ftp, tgp
     *               - Per-game (1 decimal): mpg, fgmpg, fgapg, ftmpg, ftapg, tgmpg, tgapg, orbpg, rpg, apg, spg, tpg, bpg, fpg, ppg
     *               - Quality Assessment: qa (1 decimal)
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
     * @return array Array of 20 sort option labels in order:
     *               ["PPG", "REB", "OREB", "AST", "STL", "BLK", "TO", "FOUL",
     *                "QA", "FGM", "FGA", "FG%", "FTM", "FTA", "FT%",
     *                "TGM", "TGA", "TG%", "GAMES", "MIN"]
     */
    public function getSortOptions(): array;
}
