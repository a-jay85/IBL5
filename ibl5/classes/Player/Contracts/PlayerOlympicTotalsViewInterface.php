<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerOlympicTotalsViewInterface - Contract for Olympics totals view
 * 
 * Renders season-by-season totals for Olympics statistics.
 */
interface PlayerOlympicTotalsViewInterface extends PlayerViewInterface
{
    /**
     * Render Olympics totals table with career totals row
     * 
     * Shows totals for each Olympics season including games, minutes,
     * and all statistical categories, plus a career totals row at the bottom.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderTotals(string $playerName): string;
}
