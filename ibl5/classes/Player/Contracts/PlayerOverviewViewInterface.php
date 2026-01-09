<?php

declare(strict_types=1);

namespace Player\Contracts;

use Player\Player;
use Player\PlayerStats;


/**
 * PlayerOverviewViewInterface - Contract for player overview page view
 * 
 * Renders the player overview page including ratings, free agency preferences,
 * and current season game log.
 */
interface PlayerOverviewViewInterface extends PlayerViewInterface
{
    /**
     * Render the complete overview page
     * 
     * Includes player ratings, free agency preferences, and game log.
     * 
     * @param int $playerID Player ID for game log queries
     * @param Player $player Player object with all data
     * @param \PlayerStats $playerStats Player stats object
     * @param \Season $season Season object for date calculations
     * @param \Shared $sharedFunctions Shared utility functions
     * @return string Rendered HTML content
     */
    public function renderOverview(
        int $playerID,
        Player $player, 
        PlayerStats $playerStats,
        \Season $season,
        \Shared $sharedFunctions
    ): string;
}
