<?php

declare(strict_types=1);

namespace LeagueStats\Contracts;

/**
 * Interface for League Stats HTML rendering
 *
 * Generates HTML output for league-wide statistics display including:
 * - Team Offense Totals table
 * - Team Defense Totals table
 * - Team Offense Averages table
 * - Team Defense Averages table
 * - Offense/Defense Differentials table
 *
 * Uses HtmlSanitizer::safeHtmlOutput() for XSS protection on team names.
 *
 * @see \LeagueStats\LeagueStatsView for implementation
 */
interface LeagueStatsViewInterface
{
    /**
     * Render the complete league statistics display
     *
     * Generates five sortable HTML tables with team statistics.
     * Highlights the current user's team row with bgcolor=#FFA.
     * Applies HtmlSanitizer::safeHtmlOutput() to team_city and team_name.
     *
     * @param array $data Combined data structure containing:
     *                    - 'teams': Processed team statistics
     *                    - 'league': League totals and averages
     *                    - 'differentials': Team differentials
     * @param int $userTeamId The current user's team ID for row highlighting
     * @return string Complete HTML output for the league stats page
     */
    public function render(array $data, int $userTeamId): string;
}
