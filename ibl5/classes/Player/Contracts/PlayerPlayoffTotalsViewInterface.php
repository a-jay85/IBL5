<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerPlayoffTotalsViewInterface - Contract for playoff totals view
 * 
 * Renders season-by-season totals for playoff statistics.
 */
interface PlayerPlayoffTotalsViewInterface extends PlayerViewInterface
{
    /**
     * Render playoff totals table with career totals row
     * 
     * Shows totals for each playoff season including games, minutes, and all
     * statistical categories, plus a career totals row at the bottom.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderTotals(string $playerName): string;
}
