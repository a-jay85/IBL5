<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerGameLogViewInterface - Contract for player game log rendering
 * 
 * Defines methods for rendering player sim-by-sim statistics HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerGameLogViewInterface
{
    /**
     * Render sim-by-sim statistics table
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string HTML table showing per-sim averages:
     *  - Sim number, games played
     *  - Minutes, FG%, FT%, 3G%
     *  - ORB, REB, AST, STL, TO, BLK, PF, PTS
     */
    public function renderSimStats(int $playerID): string;
}
