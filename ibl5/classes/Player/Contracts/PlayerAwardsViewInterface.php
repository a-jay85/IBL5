<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerAwardsViewInterface - Contract for player awards rendering
 * 
 * Defines methods for rendering player awards and All-Star activity HTML.
 * All methods return HTML strings using output buffering pattern.
 */
interface PlayerAwardsViewInterface
{
    /**
     * Render All-Star activity table
     * 
     * @param string $playerName Player name to fetch awards for
     * @return string HTML for All-Star activity table showing:
     *  - All-Star Game count
     *  - Three-Point Contest count
     *  - Slam Dunk Competition count
     *  - Rookie-Sophomore Challenge count
     */
    public function renderAllStarActivity(string $playerName): string;

    /**
     * Render full awards list table
     * 
     * @param string $playerName Player name to fetch awards for
     * @return string HTML table with Year and Award columns
     */
    public function renderAwardsList(string $playerName): string;
}
