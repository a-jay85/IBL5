<?php

declare(strict_types=1);

namespace PowerRankings\Contracts;

/**
 * PowerRankingsViewInterface - Contract for power rankings view rendering
 *
 * Defines methods for generating HTML output for power rankings.
 *
 * @see \PowerRankings\PowerRankingsView For the concrete implementation
 */
interface PowerRankingsViewInterface
{
    /**
     * Render the complete power rankings table
     *
     * @param array $rankings Power rankings data
     * @param int $seasonEndingYear Season ending year for title
     * @return string HTML output
     */
    public function render(array $rankings, int $seasonEndingYear): string;
}
