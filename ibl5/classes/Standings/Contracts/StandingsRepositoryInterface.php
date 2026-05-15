<?php

declare(strict_types=1);

namespace Standings\Contracts;

/**
 * StandingsRepositoryInterface - Contract for standings data access
 *
 * Defines methods for retrieving and updating team standings data.
 * Implementations must provide data for conferences, divisions, and team streaks.
 *
 * @phpstan-type StandingsRow array{teamid: int, team_name: string, league_record: string, pct: string, gamesBack: string, conf_record: string, div_record: string, home_record: string, away_record: string, games_unplayed: int, magicNumber: int|string, clinched_conference: int, clinched_division: int, clinched_playoffs: int, clinched_league: int, wins: int, homeGames: int, awayGames: int, color1: string, color2: string}
 * @phpstan-type BulkStandingsRow array{teamid: int, team_name: string, league_record: string, pct: string, conf_gb: string, div_gb: string, conf_record: string, div_record: string, home_record: string, away_record: string, games_unplayed: int, conf_magic_number: int|string, div_magic_number: int|string, clinched_conference: int, clinched_division: int, clinched_playoffs: int, clinched_league: int, wins: int, homeGames: int, awayGames: int, conference: string, division: string, color1: string, color2: string}
 * @phpstan-type StreakRow array{last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int, sos: float|string, remaining_sos: float|string, sos_rank: int, remaining_sos_rank: int}
 * @phpstan-type PythagoreanStats array{pointsScored: int, pointsAllowed: int}
 * @phpstan-type SeriesRecordRow array{self: int, opponent: int, wins: int, losses: int}
 * @phpstan-type TeamMapping array{conference: string, division: string, teamName: string}
 * @phpstan-type UpsertStandingsParams array{teamid: int, teamName: string, leagueRecord: string, wins: int, losses: int, pct: float, gamesUnplayed: int, conference: string, confGb: float, confRecord: string, division: string, divGb: float, divRecord: string, homeRecord: string, awayRecord: string, confWins: int, confLosses: int, divWins: int, divLosses: int, homeWins: int, homeLosses: int, awayWins: int, awayLosses: int}
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

    /**
     * Upsert a team's standings row (INSERT...ON DUPLICATE KEY UPDATE)
     *
     * @param UpsertStandingsParams $params
     */
    public function upsertStandings(array $params): void;

    /**
     * Update a team's magic number for a specific grouping column
     *
     * @param int $teamid Team ID
     * @param int $magicNumber Computed magic number
     * @param string $magicNumberColumn Column name (conf_magic_number or div_magic_number)
     */
    public function updateMagicNumber(int $teamid, int $magicNumber, string $magicNumberColumn): void;

    /**
     * Set a clinched flag for a team
     *
     * @param string $teamName Team name
     * @param string $clinchedColumn Column name (clinched_conference, clinched_division, clinched_league, clinched_playoffs)
     */
    public function updateClinchedFlag(string $teamName, string $clinchedColumn): void;

    /**
     * Upsert a team award (INSERT...ON DUPLICATE KEY UPDATE)
     *
     * @param int $seasonYear Season ending year
     * @param string $teamName Team name
     * @param string $awardName Award name
     */
    public function upsertTeamAward(int $seasonYear, string $teamName, string $awardName): void;

    /**
     * Fetch teams in a region ordered by pct for magic number computation
     *
     * @param string $grouping Column name (conference or division)
     * @param string $region Region value
     * @return list<array{teamid: int, team_name: string, home_wins: int, home_losses: int, away_wins: int, away_losses: int}>
     */
    public function fetchTeamsByRegion(string $grouping, string $region): array;

    /**
     * Fetch top 2 teams by wins for clinch checking
     *
     * @param string|null $grouping Column name, or null for league-wide
     * @param string|null $region Region value, or null for league-wide
     * @return list<array{teamid: int, team_name: string, wins: int}>
     */
    public function fetchTopTeamsByWins(?string $grouping, ?string $region): array;

    /**
     * Fetch the team with the fewest losses (excluding a given team)
     *
     * @param string $excludeTeamName Team name to exclude
     * @param string|null $grouping Column name, or null for league-wide
     * @param string|null $region Region value, or null for league-wide
     * @return array{losses: int}|null
     */
    public function fetchLeastLosingTeam(string $excludeTeamName, ?string $grouping, ?string $region): ?array;

    /**
     * Check if all teams in a region (or league-wide) have finished the season
     *
     * @param string|null $grouping Column name, or null for league-wide
     * @param string|null $region Region value, or null for league-wide
     */
    public function isRegionSeasonOver(?string $grouping, ?string $region): bool;

    /**
     * Get the head-to-head winner between two teams
     *
     * @param int $tid1 First team ID
     * @param int $tid2 Second team ID
     * @param string $startDate Season start date (YYYY-MM-DD)
     * @param string $endDate Season end date (YYYY-MM-DD)
     * @return int The teamid of the head-to-head winner
     */
    public function getHeadToHeadWinner(int $tid1, int $tid2, string $startDate, string $endDate): int;

    /**
     * Fetch conference/division mapping from league config for a season
     *
     * @param int $seasonEndingYear Season ending year
     * @return array<int, TeamMapping>
     */
    public function fetchTeamMapForSeason(int $seasonEndingYear): array;

    /**
     * Fetch all played games for a season
     *
     * @param string $startDate Season start date (YYYY-MM-DD)
     * @param string $endDate Season end date (YYYY-MM-DD)
     * @return list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}>
     */
    public function fetchPlayedGamesForSeason(string $startDate, string $endDate): array;

    /**
     * Fetch the 8 winningest teams in a conference for playoff clinch check
     *
     * @param string $conference Conference name
     * @return list<array{team_name: string, wins: int}>
     */
    public function fetchWinningestTeams(string $conference): array;

    /**
     * Fetch the 6 teams with the most losses in a conference for playoff clinch check
     *
     * @param string $conference Conference name
     * @return list<array{losses: int}>
     */
    public function fetchMostLosingTeams(string $conference): array;
}
