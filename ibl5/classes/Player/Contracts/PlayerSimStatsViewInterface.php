<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerSimStatsViewInterface - Contract for sim-by-sim stats view
 * 
 * Renders player statistics broken down by simulation period.
 */
interface PlayerSimStatsViewInterface extends PlayerViewInterface
{
    /**
     * Render sim-by-sim statistics table
     * 
     * Shows averages for each simulation period including games played,
     * minutes, shooting percentages, and all other statistical categories.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderSimStats(int $playerID): string;
}
