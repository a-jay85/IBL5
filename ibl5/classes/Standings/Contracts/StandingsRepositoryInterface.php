<?php

declare(strict_types=1);

namespace Standings\Contracts;

/**
 * StandingsRepositoryInterface - Contract for standings data access
 *
 * Defines methods for retrieving team standings data from the database.
 * Implementations must provide data for conferences, divisions, and team streaks.
 *
 * @phpstan-type StandingsRow array{tid: int, team_name: string, leagueRecord: string, pct: string, gamesBack: string, confRecord: string, divRecord: string, homeRecord: string, awayRecord: string, gamesUnplayed: int, magicNumber: int|string, clinchedConference: int, clinchedDivision: int, clinchedPlayoffs: int, homeGames: int, awayGames: int, color1: string, color2: string}
 * @phpstan-type StreakRow array{last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int}
 * @phpstan-type PythagoreanStats array{pointsScored: int, pointsAllowed: int}
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
     * @return PythagoreanStats|null Array with 'pointsScored' and 'pointsAllowed' or null if not found
     */
    public function getTeamPythagoreanStats(int $teamId): ?array;
}
