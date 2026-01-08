<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerGameLogViewInterface - Contract for player game log view
 * 
 * Renders player game logs and sim-by-sim statistics.
 */
interface PlayerGameLogViewInterface
{
    /**
     * Render sim-by-sim statistics table
     * 
     * Shows averages for each simulation period including games played,
     * minutes, shooting percentages, rebounds, assists, etc.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML for sim stats table
     */
    public function renderSimStats(int $playerID): string;

    /**
     * Render detailed game log table
     * 
     * Shows game-by-game statistics for a date range.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return string HTML for game log table
     */
    public function renderGameLog(int $playerID, string $startDate, string $endDate): string;
}
