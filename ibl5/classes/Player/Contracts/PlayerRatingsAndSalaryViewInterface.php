<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerRatingsAndSalaryViewInterface - Contract for ratings and salary history view
 * 
 * Renders player ratings by year and salary history.
 */
interface PlayerRatingsAndSalaryViewInterface extends PlayerViewInterface
{
    /**
     * Render ratings by year table with salary history
     * 
     * Shows season-by-season ratings (all 22 rating categories) and salary,
     * plus total career earnings at the bottom.
     * 
     * @param int $playerID Player ID to fetch ratings for
     * @return string Rendered HTML content
     */
    public function renderRatingsAndSalary(int $playerID): string;
}
