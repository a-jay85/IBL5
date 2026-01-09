<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerRegularSeasonAveragesViewInterface - Contract for regular season averages view
 * 
 * Renders season-by-season per-game averages for regular season statistics.
 */
interface PlayerRegularSeasonAveragesViewInterface extends PlayerViewInterface
{
    /**
     * Render regular season averages table with career averages row
     * 
     * Shows per-game averages for each season including minutes, shooting
     * percentages, and all statistical categories, plus career averages.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderAverages(int $playerID): string;
}
