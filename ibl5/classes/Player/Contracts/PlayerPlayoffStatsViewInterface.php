<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerPlayoffStatsViewInterface - Contract for playoff stats rendering
 * 
 * Defines methods for rendering playoff totals and averages HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerPlayoffStatsViewInterface
{
    /**
     * Render playoff totals table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-season playoff totals
     */
    public function renderPlayoffTotals(int $playerID): string;

    /**
     * Render playoff averages table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-game playoff averages
     */
    public function renderPlayoffAverages(int $playerID): string;
}
