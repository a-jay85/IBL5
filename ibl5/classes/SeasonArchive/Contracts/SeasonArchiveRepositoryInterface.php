<?php

declare(strict_types=1);

namespace SeasonArchive\Contracts;

/**
 * SeasonArchiveRepositoryInterface - Contract for season archive data access
 *
 * Defines methods for retrieving season archive data from the database,
 * including awards, playoff results, team awards, HEAT standings, and GM history.
 *
 * @phpstan-type AwardRow array{year: int, Award: string, name: string, table_ID: int}
 * @phpstan-type PlayoffRow array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int}
 * @phpstan-type TeamAwardRow array{year: int, name: string, Award: string, ID: int}
 * @phpstan-type GmAwardWithTeamRow array{year: int, Award: string, gm_username: string, team_name: string, table_ID: int}
 * @phpstan-type GmTenureWithTeamRow array{gm_username: string, start_season_year: int, end_season_year: int|null, team_name: string}
 * @phpstan-type HeatWinLossRow array{year: int, currentname: string, namethatyear: string, wins: int, losses: int, table_ID: int}
 * @phpstan-type TeamColorRow array{teamid: int, team_name: string, color1: string, color2: string}
 *
 * @see \SeasonArchive\SeasonArchiveRepository For the concrete implementation
 */
interface SeasonArchiveRepositoryInterface
{
    /**
     * Get all distinct season years from the awards table
     *
     * @return list<int> Distinct years sorted ascending
     */
    public function getAllSeasonYears(): array;

    /**
     * Get all awards for a given year
     *
     * @param int $year Season ending year
     * @return list<AwardRow> Array of award records
     */
    public function getAwardsByYear(int $year): array;

    /**
     * Get playoff results for a given year, derived from box score data
     *
     * @param int $year Season ending year
     * @return list<PlayoffRow> Array of playoff results ordered by round
     */
    public function getPlayoffResultsByYear(int $year): array;

    /**
     * Get team awards for a given year
     *
     * The year column in ibl_team_awards is wrapped in HTML tags (e.g. "<B>1989</B>"),
     * so this uses LIKE matching.
     *
     * @param int $year Season ending year
     * @return list<TeamAwardRow> Array of team award records
     */
    public function getTeamAwardsByYear(int $year): array;

    /**
     * Get all GM awards with associated team names (via tenure JOIN)
     *
     * @return list<GmAwardWithTeamRow> Array of GM award records with team names
     */
    public function getAllGmAwardsWithTeams(): array;

    /**
     * Get all GM tenures with associated team names
     *
     * @return list<GmTenureWithTeamRow> Array of GM tenure records with team names
     */
    public function getAllGmTenuresWithTeams(): array;

    /**
     * Get HEAT win/loss standings for a given HEAT year
     *
     * Note: HEAT year = ending year - 1 (e.g., Season I ending year 1989 uses HEAT year 1988)
     *
     * @param int $heatYear HEAT year (ending year minus 1)
     * @return list<HeatWinLossRow> Array of HEAT standings ordered by wins DESC
     */
    public function getHeatWinLossByYear(int $heatYear): array;

    /**
     * Get team colors for all active teams
     *
     * @return array<string, array{color1: string, color2: string, teamid: int}> Map of team_name => colors + teamid
     */
    public function getTeamColors(): array;

    /**
     * Batch-lookup player IDs by name
     *
     * @param list<string> $names Player names to look up
     * @return array<string, int> Map of player name => pid (only found names included)
     */
    public function getPlayerIdsByNames(array $names): array;

    /**
     * Get team conference assignments from standings
     *
     * @return array<string, string> Map of team_name => 'Eastern'|'Western'
     */
    public function getTeamConferences(): array;
}
