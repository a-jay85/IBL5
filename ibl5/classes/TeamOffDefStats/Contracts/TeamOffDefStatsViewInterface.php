<?php

declare(strict_types=1);

namespace TeamOffDefStats\Contracts;

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
 * @see \TeamOffDefStats\TeamOffDefStatsView for implementation
 *
 * @phpstan-import-type ProcessedTeamStats from TeamOffDefStatsServiceInterface
 * @phpstan-import-type LeagueTotals from TeamOffDefStatsServiceInterface
 * @phpstan-import-type DifferentialTeam from TeamOffDefStatsServiceInterface
 *
 * @phpstan-type RenderData array{teams: list<ProcessedTeamStats>, league: LeagueTotals, differentials: list<DifferentialTeam>}
 */
interface TeamOffDefStatsViewInterface
{
    /**
     * Render the complete league statistics display
     *
     * Generates five sortable HTML tables with team statistics.
     * User's team rows are highlighted client-side via user-team-highlighter.js.
     * Applies HtmlSanitizer::safeHtmlOutput() to team_city and team_name.
     *
     * @param RenderData $data Combined data structure containing:
     *                         - 'teams': Processed team statistics
     *                         - 'league': League totals and averages
     *                         - 'differentials': Team differentials
     * @return string Complete HTML output for the league stats page
     */
    public function render(array $data): string;
}
