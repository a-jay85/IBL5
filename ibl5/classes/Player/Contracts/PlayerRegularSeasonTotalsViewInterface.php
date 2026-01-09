<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerRegularSeasonTotalsViewInterface - Contract for regular season totals view
 * 
 * Renders season-by-season totals for regular season statistics.
 */
interface PlayerRegularSeasonTotalsViewInterface extends PlayerViewInterface
{
    /**
     * Render regular season totals table with career totals row
     * 
     * Shows totals for each season including games, minutes, and all
     * statistical categories, plus a career totals row at the bottom.
     * 
     * @param int $playerID Player ID to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderTotals(int $playerID): string;
}
