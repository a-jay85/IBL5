<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerPlayoffStatsViewInterface - Contract for playoff stats view
 * 
 * Renders playoff totals and averages tables.
 */
interface PlayerPlayoffStatsViewInterface
{
    /**
     * Render playoff totals table
     * 
     * Shows season-by-season playoff totals for all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for playoff totals table
     */
    public function renderPlayoffTotals(string $playerName): string;

    /**
     * Render playoff averages table
     * 
     * Shows season-by-season playoff per-game averages for all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for playoff averages table
     */
    public function renderPlayoffAverages(string $playerName): string;
}
