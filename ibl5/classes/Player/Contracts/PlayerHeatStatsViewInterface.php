<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerHeatStatsViewInterface - Contract for HEAT tournament stats rendering
 * 
 * Defines methods for rendering HEAT tournament totals and averages HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerHeatStatsViewInterface
{
    /**
     * Render HEAT tournament totals table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-season HEAT totals
     */
    public function renderHeatTotals(int $playerID): string;

    /**
     * Render HEAT tournament averages table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-game HEAT averages
     */
    public function renderHeatAverages(int $playerID): string;
}
