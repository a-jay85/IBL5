<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerPlayoffAveragesViewInterface - Contract for playoff averages view
 * 
 * Renders season-by-season per-game averages for playoff statistics.
 */
interface PlayerPlayoffAveragesViewInterface extends PlayerViewInterface
{
    /**
     * Render playoff averages table with career averages row
     * 
     * Shows per-game averages for each playoff season including minutes,
     * shooting percentages, and all statistical categories.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderAverages(string $playerName): string;
}
