<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamRepositoryInterface - Contract for Team data access operations
 *
 * Defines methods for querying team information from multiple database tables:
 * power rankings, standings, banners, history, rosters, and playoff results.
 *
 * All methods use prepared statements internally.
 * All methods return arrays, never throw exceptions.
 *
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type PowerRow array{TeamID: int, Team: string, Division: string, Conference: string, ranking: float, win: int, loss: int, gb: float, conf_win: int, conf_loss: int, div_win: int, div_loss: int, home_win: int, home_loss: int, road_win: int, road_loss: int, last_win: int, last_loss: int, streak_type: string, streak: int, created_at: string, updated_at: string}
 * @phpstan-type BannerRow array{year: int, currentname: string, bannername: string, bannertype: int}
 * @phpstan-type GMHistoryRow array{year: string, name: string, Award: string, prim: int}
 * @phpstan-type TeamAwardRow array{year: string, name: string, Award: string, ID: int}
 * @phpstan-type WinLossRow array{year: string, currentname: string, namethatyear: string, wins: string, losses: string, table_ID: int}
 * @phpstan-type HEATWinLossRow array{year: int, currentname: string, namethatyear: string, wins: int, losses: int, table_ID: int}
 * @phpstan-type PlayoffResultRow array{year: int, round: int, winner: string, loser: string, loser_games: int, id: int}
 * @phpstan-type HistRow array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_do: int, r_po: int, r_to: int, r_od: int, r_dd: int, r_pd: int, r_td: int, salary: int, nuke_iblhist: int, created_at: string, updated_at: string}
 */
interface TeamRepositoryInterface
{
    /**
     * Get team information by team ID
     *
     * @param int $teamID Team ID from ibl_team_info
     * @return TeamInfoRow|null Team data or null if not found
     */
    public function getTeam(int $teamID): ?array;

    /**
     * Get team power ranking data
     *
     * @param string $teamName Team name to search for
     * @return PowerRow|null Complete row from ibl_power or null if not found
     */
    public function getTeamPowerData(string $teamName): ?array;

    /**
     * Get all teams in a specific division with standings
     *
     * @param string $division Division name (e.g., "Atlantic", "Central", "Pacific")
     * @return list<PowerRow> Rows ordered by gb DESC
     */
    public function getDivisionStandings(string $division): array;

    /**
     * Get all teams in a specific conference with standings
     *
     * @param string $conference Conference name (e.g., "Eastern", "Western")
     * @return list<PowerRow> Rows ordered by gb DESC
     */
    public function getConferenceStandings(string $conference): array;

    /**
     * Get championship banners (championships won) for a team
     *
     * @param string $teamName Team name to search for
     * @return list<BannerRow> Rows ordered by year ASC
     */
    public function getChampionshipBanners(string $teamName): array;

    /**
     * Get GM history for a team
     *
     * Records match format: "Owner Name (Team Name)"
     *
     * @param string $ownerName Owner/GM name
     * @param string $teamName Team name
     * @return list<GMHistoryRow> Rows ordered by year ASC
     */
    public function getGMHistory(string $ownerName, string $teamName): array;

    /**
     * Get team accomplishments and awards
     *
     * @param string $teamName Team name to search for
     * @return list<TeamAwardRow> Rows ordered by year DESC
     */
    public function getTeamAccomplishments(string $teamName): array;

    /**
     * Get regular season win/loss history for a team
     *
     * @param string $teamName Team name to search for
     * @return list<WinLossRow> Rows ordered by year DESC
     */
    public function getRegularSeasonHistory(string $teamName): array;

    /**
     * Get HEAT tournament results for a team
     *
     * @param string $teamName Team name to search for
     * @return list<HEATWinLossRow> Rows ordered by year DESC
     */
    public function getHEATHistory(string $teamName): array;

    /**
     * Get playoff results for all teams
     *
     * @return list<PlayoffResultRow> Rows ordered by year DESC
     */
    public function getPlayoffResults(): array;

    /**
     * Get free agency roster for a team (expiring contracts only)
     *
     * @param int $teamID Team ID
     * @return list<PlayerRow> Player rows ordered by ordinal, then name
     */
    public function getFreeAgencyRoster(int $teamID): array;

    /**
     * Get current season roster for a team
     *
     * @param int $teamID Team ID
     * @return list<PlayerRow> Player rows ordered by ordinal, then name
     */
    public function getRosterUnderContract(int $teamID): array;

    /**
     * Get free agents available for signing
     *
     * @param bool $includeFreeAgencyActive If true, only show expiring contracts (cyt != cy)
     * @return list<PlayerRow> Player rows ordered by ordinal ASC
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): array;

    /**
     * Get entire league roster
     *
     * @return list<PlayerRow> Player rows ordered by ordinal ASC
     */
    public function getEntireLeagueRoster(): array;

    /**
     * Get historical roster for a team in a specific season
     *
     * @param int $teamID Team ID
     * @param string $year Season year (e.g., "2023", "2024")
     * @return list<HistRow> Player rows ordered by name ASC
     */
    public function getHistoricalRoster(int $teamID, string $year): array;
}
