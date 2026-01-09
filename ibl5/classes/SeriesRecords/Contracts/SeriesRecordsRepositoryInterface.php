<?php

declare(strict_types=1);

namespace SeriesRecords\Contracts;

/**
 * SeriesRecordsRepositoryInterface - Contract for Series Records data access operations
 * 
 * Defines methods for querying head-to-head series records between teams
 * from the ibl_schedule table and team information from ibl_team_info.
 * 
 * All methods use prepared statements and safe escaping internally.
 * All methods return database result objects or arrays, never throw exceptions.
 */
interface SeriesRecordsRepositoryInterface
{
    /**
     * Get all teams with basic info for series records display
     * 
     * @return array<int, array<string, mixed>> Array of team data ordered by teamid
     * 
     * **Return Structure:**
     * Each element contains:
     * - teamid: int - Team ID
     * - team_city: string - City name
     * - team_name: string - Team name
     * - color1: string - Primary team color (hex)
     * - color2: string - Secondary team color (hex)
     * 
     * **Behaviors:**
     * - Excludes placeholder teams (teamid = 99 and FREE_AGENTS_TEAMID)
     * - Returns empty array if no teams found
     * - Ordered by teamid ascending
     */
    public function getTeamsForSeriesRecords(): array;

    /**
     * Get all head-to-head series records between teams
     * 
     * Aggregates wins and losses for each team pairing from all historical games.
     * A win for team A is counted as a loss for team B in that matchup.
     * 
     * @return array<int, array<string, mixed>> Array of series records
     * 
     * **Return Structure:**
     * Each element contains:
     * - self: int - Team ID of the "self" team
     * - opponent: int - Team ID of the opponent team
     * - wins: int - Number of wins for "self" team against opponent
     * - losses: int - Number of losses for "self" team against opponent
     * 
     * **Behaviors:**
     * - Returns empty array if no games found
     * - Ordered by self, then opponent (ascending)
     * - Includes only completed games (where score exists)
     */
    public function getSeriesRecords(): array;

    /**
     * Get the maximum team ID (number of teams) in the schedule
     * 
     * Used to determine the grid dimensions for the series records table.
     * 
     * @return int Maximum team ID from the schedule, or 0 if no games exist
     */
    public function getMaxTeamId(): int;
}
