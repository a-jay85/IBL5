<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerAwardsViewInterface - Contract for player awards view
 * 
 * Renders player awards and All-Star activity tables.
 */
interface PlayerAwardsViewInterface
{
    /**
     * Render All-Star activity table
     * 
     * Shows counts for All-Star Games, Three-Point Contests,
     * Slam Dunk Competitions, and Rookie-Sophomore Challenges.
     * 
     * @param string $playerName Player name to fetch awards for
     * @return string HTML for All-Star activity table
     */
    public function renderAllStarActivity(string $playerName): string;

    /**
     * Render full awards list
     * 
     * Shows all awards in a table ordered by year.
     * 
     * @param string $playerName Player name to fetch awards for
     * @return string HTML for awards list table
     */
    public function renderAwardsList(string $playerName): string;
}
