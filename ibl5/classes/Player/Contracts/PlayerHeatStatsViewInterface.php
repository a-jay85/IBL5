<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerHeatStatsViewInterface - Contract for HEAT tournament stats view
 * 
 * Renders HEAT tournament totals and averages tables.
 */
interface PlayerHeatStatsViewInterface
{
    /**
     * Render HEAT totals table
     * 
     * Shows season-by-season HEAT tournament totals for all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for HEAT totals table
     */
    public function renderHeatTotals(string $playerName): string;

    /**
     * Render HEAT averages table
     * 
     * Shows season-by-season HEAT tournament per-game averages for all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string HTML for HEAT averages table
     */
    public function renderHeatAverages(string $playerName): string;
}
