<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerHeatTotalsViewInterface - Contract for HEAT tournament totals view
 * 
 * Renders season-by-season totals for HEAT tournament statistics.
 */
interface PlayerHeatTotalsViewInterface extends PlayerViewInterface
{
    /**
     * Render HEAT totals table with career totals row
     * 
     * Shows totals for each HEAT tournament season including games, minutes,
     * and all statistical categories, plus a career totals row at the bottom.
     * 
     * @param string $playerName Player name to fetch stats for
     * @return string Rendered HTML content
     */
    public function renderTotals(string $playerName): string;
}
