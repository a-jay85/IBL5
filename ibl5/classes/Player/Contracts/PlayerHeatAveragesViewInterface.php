<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerHeatAveragesViewInterface - Contract for HEAT tournament averages view
 * 
 * Renders season-by-season per-game averages for HEAT tournament statistics.
 */
interface PlayerHeatAveragesViewInterface extends PlayerViewInterface
{
    /**
     * Render HEAT averages table with career averages row
     * 
     * Shows per-game averages for each HEAT tournament season including minutes,
     * shooting percentages, and all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderAverages(string $playerName): string;
}
