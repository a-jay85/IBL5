<?php

declare(strict_types=1);

namespace Standings\Contracts;

/**
 * StandingsRepositoryInterface - Contract for standings data access
 *
 * Defines methods for retrieving team standings data from the database.
 * Implementations must provide data for conferences, divisions, and team streaks.
 *
 * @phpstan-type StandingsRow array{teamid: int, team_name: string, leagueRecord: string, pct: string, gamesBack: string, confRecord: string, divRecord: string, homeRecord: string, awayRecord: string, gamesUnplayed: int, magicNumber: int|string, clinchedConference: int, clinchedDivision: int, clinchedPlayoffs: int, clinchedLeague: int, wins: int, homeGames: int, awayGames: int, color1: string, color2: string}
 * @phpstan-type BulkStandingsRow array{teamid: int, team_name: string, leagueRecord: string, pct: string, confGB: string, divGB: string, confRecord: string, divRecord: string, homeRecord: string, awayRecord: string, gamesUnplayed: int, confMagicNumber: int|string, divMagicNumber: int|string, clinchedConference: int, clinchedDivision: int, clinchedPlayoffs: int, clinchedLeague: int, wins: int, homeGames: int, awayGames: int, conference: string, division: string, color1: string, color2: string}
 * @phpstan-type StreakRow array{last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int, sos: float|string, remaining_sos: float|string, sos_rank: int, remaining_sos_rank: int}
 * @phpstan-type PythagoreanStats array{pointsScored: int, pointsAllowed: int}
 * @phpstan-type SeriesRecordRow array{self: int, opponent: int, wins: int, losses: int}
 *
 * @see \Standings\StandingsRepository For the concrete implementation
 */
interface StandingsRepositoryInterface
{
    /**
     * Get standings for a specific region (conference or division)
     *
     * @param string $region Region name (e.g., 'Eastern', 'Atlantic')
     * @return list<StandingsRow> Array of team standings data sorted by games back
     */
    public function getStandingsByRegion(string $region): array;

    /**
     * Get standings for all teams with conference and division columns
     *
     * @return list<BulkStandingsRow>
     */
    public function getAllStandings(): array;

    /**
     * Get streak, last 10 games, and power ranking data for a team
     *
     * @param int $teamId Team ID
     * @return StreakRow|null Array with last_win, last_loss, streak_type, streak, ranking or null if not found
     */
    public function getTeamStreakData(int $teamId): ?array;

    /**
     * Get team offensive and defensive stats for Pythagorean calculation
     *
     * @param int $teamId Team ID
     * @param int $seasonYear Season ending year (e.g. 2025 for the 2024-25 season)
     * @return PythagoreanStats|null Array with 'pointsScored' and 'pointsAllowed' or null if not found
     */
    public function getTeamPythagoreanStats(int $teamId, int $seasonYear): ?array;

    /**
     * Get streak, last 10 games, and power ranking data for all teams
     *
     * @return array<int, StreakRow> Map of team ID to streak data
     */
    public function getAllStreakData(): array;

    /**
     * Get offensive and defensive stats for all teams in a season for Pythagorean calculation
     *
     * @param int $seasonYear Season ending year (e.g. 2025 for the 2024-25 season)
     * @return array<int, PythagoreanStats> Map of team ID to Pythagorean stats
     */
    public function getAllPythagoreanStats(int $seasonYear): array;

    /**
     * Get all head-to-head series records for the current season
     *
     * @return list<SeriesRecordRow> Array of series record rows
     */
    public function getSeriesRecords(): array;
}
