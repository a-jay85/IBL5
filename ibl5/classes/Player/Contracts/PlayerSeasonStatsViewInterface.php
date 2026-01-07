<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerSeasonStatsViewInterface - Contract for regular season stats rendering
 * 
 * Defines methods for rendering regular season totals and averages HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerSeasonStatsViewInterface
{
    /**
     * Render regular season totals table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-season totals:
     *  - Team, Year, Games, Minutes
     *  - FGM-FGA, FG%, FTM-FTA, FT%, 3GM-3GA, 3G%
     *  - ORB, DRB, REB, AST, STL, TO, BLK, PF, PTS
     */
    public function renderSeasonTotals(int $playerID): string;

    /**
     * Render regular season averages table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table with per-game averages:
     *  - Team, Year, Games, Minutes
     *  - FG%, FT%, 3G%
     *  - ORB, DRB, REB, AST, STL, TO, BLK, PF, PTS
     */
    public function renderSeasonAverages(int $playerID): string;
}
