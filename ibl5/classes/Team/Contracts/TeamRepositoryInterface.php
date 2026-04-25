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
 * @phpstan-type PowerRow array{teamid: int, team_name: string, league_record: string, wins: int, losses: int, pct: float|string, conference: string, division: string, conf_record: string, div_record: string, div_gb: float|string|null, home_record: string, away_record: string, games_unplayed: int, ranking: float, last_win: int, last_loss: int, streak_type: string, streak: int, sos: float|string, remaining_sos: float|string}
 * @phpstan-type BannerRow array{year: int, currentname: string, bannername: string, bannertype: int}
 * @phpstan-type GMTenureRow array{id: int, franchise_id: int, gm_display_name: string, start_season_year: int, end_season_year: int|null, is_mid_season_start: int, is_mid_season_end: int}
 * @phpstan-type GMAwardRow array{year: int, Award: string, name: string, table_ID: int}
 * @phpstan-type TeamAwardRow array{year: int, name: string, Award: string, ID: int}
 * @phpstan-type WinLossRow array{year: int, currentname: string, namethatyear: string, wins: int, losses: int}
 * @phpstan-type HEATWinLossRow array{year: int, currentname: string, namethatyear: string, wins: int, losses: int}
 * @phpstan-type PlayoffResultRow array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int, winner_name_that_year: string, loser_name_that_year: string}
 * @phpstan-type HistRow array{pid: int, name: string, year: int, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_drive_off: int, r_po: int, r_trans_off: int, r_od: int, r_dd: int, r_pd: int, r_td: int, salary: int, talent: int, skill: int, intangibles: int, tsi_sum: int, clutch: int, consistency: int, age: int, peak: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int}
 * @phpstan-type FranchiseSeasonRow array{id: int, franchise_id: int, season_year: int, season_ending_year: int, team_city: string, team_name: string}
 */
interface TeamRepositoryInterface
{
    /**
     * Get team information by team ID
     *
     * @param int $teamid Team ID from ibl_team_info
     * @return TeamInfoRow|null Team data or null if not found
     */
    public function getTeam(int $teamid): ?array;

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
     * Get GM tenures for a franchise
     *
     * @param int $franchiseId Team ID (franchise_id in ibl_gm_tenures)
     * @return list<GMTenureRow> Rows ordered by start_season_year ASC
     */
    public function getGMTenures(int $franchiseId): array;

    /**
     * Get GM awards for a specific GM
     *
     * @param string $gmUsername GM username
     * @return list<GMAwardRow> Rows ordered by year ASC
     */
    public function getGMAwards(string $gmUsername): array;

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
     * @param int $teamid Team ID
     * @return list<PlayerRow> Player rows ordered by ordinal, then name
     */
    public function getFreeAgencyRoster(int $teamid): array;

    /**
     * Get current season roster for a team
     *
     * @param int $teamid Team ID
     * @return list<PlayerRow> Player rows ordered by ordinal, then name
     */
    public function getRosterUnderContract(int $teamid): array;

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
     * @param int $teamid Team ID
     * @param string $year Season year (e.g., "2023", "2024")
     * @return list<HistRow> Player rows ordered by name ASC
     */
    public function getHistoricalRoster(int $teamid, string $year): array;

    /**
     * Get all franchise seasons for a franchise
     *
     * @param int $franchiseId Franchise ID (teamid from ibl_team_info)
     * @return list<FranchiseSeasonRow> Rows ordered by season_year ASC
     */
    public function getFranchiseSeasons(int $franchiseId): array;

    /**
     * Get all real teams (teamid 1..MAX_REAL_TEAMID) ordered by team_name
     *
     * @return list<array{teamid: int, team_name: string}> Team rows
     */
    public function getAllTeams(): array;
}
