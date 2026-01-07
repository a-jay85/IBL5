<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerOlympicsStatsViewInterface - Contract for Olympics stats rendering
 * 
 * Defines methods for rendering Olympics totals and averages HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerOlympicsStatsViewInterface
{
    /**
     * Render Olympics totals table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-event Olympics totals
     */
    public function renderOlympicsTotals(int $playerID): string;

    /**
     * Render Olympics averages table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-game Olympics averages
     */
    public function renderOlympicsAverages(int $playerID): string;
}
