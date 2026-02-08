<?php

declare(strict_types=1);

namespace SeriesRecords\Contracts;

/**
 * SeriesRecordsViewInterface - Contract for Series Records view rendering
 *
 * Handles all HTML generation for the series records grid table display.
 * Separates presentation logic from business logic and data access.
 *
 * @phpstan-import-type SeriesTeamRow from SeriesRecordsRepositoryInterface
 * @phpstan-import-type SeriesMatrix from SeriesRecordsServiceInterface
 */
interface SeriesRecordsViewInterface
{
    /**
     * Render the complete series records grid table
     *
     * Generates an HTML table showing head-to-head records between all teams.
     * The table has teams on both axes, with each cell showing the record
     * of the row team vs the column team.
     *
     * @param list<array{teamid: int, team_city: string, team_name: string, color1: string, color2: string}> $teams Array of team data
     * @param array<int, array<int, array{wins: int, losses: int}>> $seriesMatrix 2D matrix of records indexed by team IDs
     * @param int $userTeamId The logged-in user's team ID for highlighting (0 if none)
     * @param int $numTeams Total number of teams to display
     * @return string Complete HTML table for series records
     */
    public function renderSeriesRecordsTable(
        array $teams,
        array $seriesMatrix,
        int $userTeamId,
        int $numTeams
    ): string;

    /**
     * Render a single table header cell with team logo
     *
     * @param int $teamId Team ID for logo image
     * @return string HTML for the header cell
     */
    public function renderHeaderCell(int $teamId): string;

    /**
     * Render a team name cell (first column)
     *
     * @param array{teamid: int, team_city: string, team_name: string, color1: string, color2: string} $team Team data array
     * @param bool $isUserTeam Whether this is the logged-in user's team
     * @return string HTML for the team name cell
     */
    public function renderTeamNameCell(array $team, bool $isUserTeam): string;

    /**
     * Render a record cell showing wins-losses
     *
     * @param int $wins Number of wins
     * @param int $losses Number of losses
     * @param string $backgroundColor Hex color for cell background
     * @param bool $isBold Whether to bold the text (user's team involvement)
     * @return string HTML for the record cell
     */
    public function renderRecordCell(int $wins, int $losses, string $backgroundColor, bool $isBold): string;

    /**
     * Render a diagonal cell (team vs itself)
     *
     * @param bool $isUserTeam Whether this is the logged-in user's team
     * @return string HTML for the diagonal 'x' cell
     */
    public function renderDiagonalCell(bool $isUserTeam): string;
}
