<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerSeasonStatsViewInterface - Contract for regular season stats view
 * 
 * Renders regular season totals and averages tables.
 */
interface PlayerSeasonStatsViewInterface
{
    /**
     * Render regular season totals table
     * 
     * Shows season-by-season totals for all statistical categories.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for season totals table
     */
    public function renderSeasonTotals(int $playerID): string;

    /**
     * Render regular season averages table
     * 
     * Shows season-by-season per-game averages for all statistical categories.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for season averages table
     */
    public function renderSeasonAverages(int $playerID): string;
}
