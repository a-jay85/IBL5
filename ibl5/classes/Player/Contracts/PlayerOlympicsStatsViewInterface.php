<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerOlympicsStatsViewInterface - Contract for Olympics stats view
 * 
 * Renders Olympics totals and averages tables.
 */
interface PlayerOlympicsStatsViewInterface
{
    /**
     * Render Olympics totals table
     * 
     * Shows season-by-season Olympics totals for all statistical categories.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for Olympics totals table
     */
    public function renderOlympicsTotals(int $playerID): string;

    /**
     * Render Olympics averages table
     *
     * Shows season-by-season Olympics per-game averages for all statistical categories.
     *
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for Olympics averages table
     */
    public function renderOlympicsAverages(int $playerID): string;
}
