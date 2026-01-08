<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerOlympicAveragesViewInterface - Contract for Olympics averages view
 * 
 * Renders season-by-season per-game averages for Olympics statistics.
 */
interface PlayerOlympicAveragesViewInterface extends PlayerViewInterface
{
    /**
     * Render Olympics averages table with career averages row
     * 
     * Shows per-game averages for each Olympics season including minutes,
     * shooting percentages, and all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderAverages(string $playerName): string;
}
