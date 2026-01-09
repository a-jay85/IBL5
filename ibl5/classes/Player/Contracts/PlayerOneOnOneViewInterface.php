<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerOneOnOneViewInterface - Contract for One-on-One results view
 * 
 * Renders player One-on-One game results.
 */
interface PlayerOneOnOneViewInterface extends PlayerViewInterface
{
    /**
     * Render One-on-One results table
     * 
     * Shows all One-on-One games where the player participated,
     * listing wins and losses with scores and opponent names.
     * 
     * @param string $playerName Player name to fetch results for
     * @return string Rendered HTML content
     */
    public function renderOneOnOneResults(string $playerName): string;
}
