<?php

declare(strict_types=1);

namespace LeagueStarters\Contracts;

use Season\Season;
use Team\Team;

/**
 * LeagueStartersViewInterface - Contract for league starters view rendering
 *
 * Defines methods for generating HTML output for league starters.
 *
 * @see \LeagueStarters\LeagueStartersView For the concrete implementation
 */
interface LeagueStartersViewInterface
{
    /**
     * Render the complete league starters display
     *
     * @param \mysqli $db Database connection (passed through to table renderers)
     * @param Season $season Current season
     * @param array<string, array<int, \Player\Player>> $startersByPosition Starters organized by position
     * @param Team $userTeam User's team for comparison
     * @param string $display Active display tab key
     * @return string HTML output
     */
    public function render(\mysqli $db, Season $season, array $startersByPosition, Team $userTeam, string $display = 'ratings'): string;

    /**
     * Render only the position tables for HTMX partial updates.
     *
     * @param \mysqli $db Database connection (passed through to table renderers)
     * @param Season $season Current season
     * @param array<string, array<int, \Player\Player>> $startersByPosition Starters organized by position
     * @param Team $userTeam User's team for comparison
     * @param string $display Active display tab key
     * @return string HTML output (tables only, no tabs or wrapper)
     */
    public function renderTableContent(\mysqli $db, Season $season, array $startersByPosition, Team $userTeam, string $display = 'ratings'): string;
}
