<?php

declare(strict_types=1);

namespace Team;

use League\LeagueContext;
use Repositories\TeamAwardSql;
use Team\Contracts\TeamRepositoryInterface;

/**
 * @phpstan-import-type TeamInfoRow from \Repositories\Contracts\TeamIdentityRepositoryInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type PowerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type BannerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMTenureRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type WinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type HEATWinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type PlayoffResultRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type HistRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type FranchiseSeasonRow from Contracts\TeamRepositoryInterface
 *
 * @see TeamRepositoryInterface
 */
class TeamRepository extends \Database\BaseMysqliRepository implements TeamRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see TeamRepositoryInterface::getTeam()
     * @return TeamInfoRow|null
     */
    public function getTeam(int $teamid): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM `ibl_team_info` WHERE teamid = ?",
            "i",
            $teamid
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamPowerData()
     * @return PowerRow|null
     */
    public function getTeamPowerData(string $teamName): ?array
    {
        /** @var PowerRow|null */
        return $this->fetchOne(
            "SELECT s.teamid, s.team_name, s.league_record, s.wins, s.losses, s.pct,
                s.conference, s.division, s.conf_record, s.div_record, s.div_gb,
                s.home_record, s.away_record, s.games_unplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM `ibl_standings` s
            JOIN `ibl_power` p ON s.teamid = p.teamid
            JOIN `ibl_team_info` t ON s.teamid = t.teamid
            WHERE t.team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getDivisionStandings()
     * @return list<PowerRow>
     */
    public function getDivisionStandings(string $division): array
    {
        /** @var list<PowerRow> */
        return $this->fetchAll(
            "SELECT s.teamid, s.team_name, s.league_record, s.wins, s.losses, s.pct,
                s.conference, s.division, s.conf_record, s.div_record, s.div_gb,
                s.home_record, s.away_record, s.games_unplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM `ibl_standings` s
            JOIN `ibl_power` p ON s.teamid = p.teamid
            WHERE s.division = ?
            ORDER BY s.div_gb ASC, s.teamid ASC",
            "s",
            $division
        );
    }

    /**
     * @see TeamRepositoryInterface::getConferenceStandings()
     * @return list<PowerRow>
     */
    public function getConferenceStandings(string $conference): array
    {
        /** @var list<PowerRow> */
        return $this->fetchAll(
            "SELECT s.teamid, s.team_name, s.league_record, s.wins, s.losses, s.pct,
                s.conference, s.division, s.conf_record, s.div_record, s.div_gb,
                s.home_record, s.away_record, s.games_unplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM `ibl_standings` s
            JOIN `ibl_power` p ON s.teamid = p.teamid
            WHERE s.conference = ?
            ORDER BY s.conf_gb ASC, s.teamid ASC",
            "s",
            $conference
        );
    }

    /**
     * @see TeamRepositoryInterface::getChampionshipBanners()
     * @return list<BannerRow>
     */
    public function getChampionshipBanners(string $teamName): array
    {
        /** @var list<BannerRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_banners` WHERE currentname = ? ORDER BY year ASC, id ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getGMTenures()
     * @return list<GMTenureRow>
     */
    public function getGMTenures(int $franchiseId): array
    {
        /** @var list<GMTenureRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_gm_tenures` WHERE franchise_id = ? ORDER BY start_season_year ASC, id ASC",
            "i",
            $franchiseId
        );
    }

    /**
     * @see TeamRepositoryInterface::getGMAwards()
     * @return list<GMAwardRow>
     */
    public function getGMAwards(string $gmUsername): array
    {
        /** @var list<GMAwardRow> */
        return $this->fetchAll(
            // @phpstan-ignore ibl.orderByMissingTiebreaker (WHERE fixes name and uk_year_award_name makes (year, award) unique, so award completes a total order)
            "SELECT * FROM `ibl_gm_awards` WHERE name = ? ORDER BY year ASC, award ASC",
            "s",
            $gmUsername
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamAccomplishments()
     * @return list<TeamAwardRow>
     */
    public function getTeamAccomplishments(string $teamName): array
    {
        /** @var list<TeamAwardRow> */
        return $this->fetchAll(
            self::buildTeamAccomplishmentsQuery(),
            "ssss",
            $teamName,
            $teamName,
            $teamName,
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getRegularSeasonHistory()
     * @return list<WinLossRow>
     */
    public function getRegularSeasonHistory(string $teamName): array
    {
        /** @var list<WinLossRow> */
        return $this->getSeasonHistory($teamName, 1);
    }

    /**
     * @see TeamRepositoryInterface::getHEATHistory()
     * @return list<HEATWinLossRow>
     */
    public function getHEATHistory(string $teamName): array
    {
        /** @var list<HEATWinLossRow> */
        return $this->getSeasonHistory($teamName, 3);
    }

    /**
     * Fetch a team's season win-loss history for a single game type.
     *
     * Regular-season and H.E.A.T. histories differ only by the `game_type`
     * filter, so both public methods delegate here.
     *
     * @param int $gameType 1 = regular season, 3 = H.E.A.T.
     * @return list<WinLossRow>
     */
    private function getSeasonHistory(string $teamName, int $gameType): array
    {
        /** @var list<WinLossRow> */
        return $this->fetchAll(
            // @phpstan-ignore ibl.orderByMissingTiebreaker (PK is (team_id, year, game_type) and WHERE fixes game_type, so team_id completes a total order)
            "SELECT `year`, currentname, namethatyear, wins, losses
             FROM `ibl_team_season_records`
             WHERE currentname = ? AND game_type = ?
             ORDER BY `year` DESC, team_id ASC",
            "si",
            $teamName,
            $gameType
        );
    }

    /**
     * Build inlined team accomplishments query with name predicate pushed into each UNION branch.
     *
     * Uses window functions instead of correlated subqueries to avoid
     * re-materializing vw_playoff_series_results per row.
     */
    private static function buildTeamAccomplishmentsQuery(): string
    {
        return "SELECT year, name, award, id
            FROM `ibl_team_awards`
            WHERE name = ?

            UNION ALL

            SELECT ranked.year, ranked.name, 'IBL Champions' AS award, 0 AS id
            FROM (
                SELECT
                    psr.year,
                    psr.winner AS name,
                    psr.round,
                    MAX(psr.round) OVER (PARTITION BY psr.year) AS max_round,
                    COUNT(*) OVER (PARTITION BY psr.year, psr.round) AS series_in_round
                FROM vw_playoff_series_results psr
            ) ranked
            WHERE ranked.round = ranked.max_round
              AND ranked.series_in_round = 1
              AND ranked.name = ?

            UNION ALL

            SELECT ranked.year, ranked.name, ranked.award, 0 AS id
            FROM (
                SELECT
                    psr.year AS year,
                    psr.winner AS name,
                    CONCAT(lc.conference, ' Conference Champions') AS award,
                    COUNT(*) OVER (PARTITION BY psr.year, lc.conference) AS series_in_conf
                " . TeamAwardSql::CONFERENCE_CHAMPION_JOIN . "
                WHERE psr.round = 3
                  AND psr.winner_games >= NULLIF(CAST(SUBSTRING_INDEX(lc.playoff_round3_format, ' ', 1) AS UNSIGNED), 0)
            ) ranked
            WHERE ranked.series_in_conf = 1
              AND ranked.name = ?

            UNION ALL

            SELECT hc.year, ti.team_name AS name, 'IBL HEAT Champions' AS award, 0 AS id
            FROM (
                " . TeamAwardSql::HEAT_WINNER_SUBSELECT . "
            ) hc
            JOIN `ibl_team_info` ti ON ti.teamid = hc.winner_tid
            WHERE hc.rn = 1 AND ti.team_name = ?

            ORDER BY year DESC, " . TeamAwardSql::AWARD_HIERARCHY_CASE . ", award ASC, id ASC";
    }

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     * @return list<PlayoffResultRow>
     */
    public function getPlayoffResults(string $teamName): array
    {
        /** @var list<PlayoffResultRow> */
        return $this->fetchAll(
            // @phpstan-ignore ibl.orderByMissingTiebreaker (the appended terms complete the ibl_playoff_series_results PK (year, round, winner_tid, loser_tid); the table has no surrogate id)
            "SELECT pr.`year`, pr.`round`, pr.winner, pr.loser, pr.winner_games, pr.loser_games,
                    COALESCE(wfs.team_name, pr.winner) AS winner_name_that_year,
                    COALESCE(lfs.team_name, pr.loser) AS loser_name_that_year
             FROM `ibl_playoff_series_results` pr
             LEFT JOIN `ibl_franchise_seasons` wfs ON wfs.franchise_id = pr.winner_tid AND wfs.season_ending_year = pr.`year`
             LEFT JOIN `ibl_franchise_seasons` lfs ON lfs.franchise_id = pr.loser_tid AND lfs.season_ending_year = pr.`year`
             WHERE pr.winner = ? OR pr.loser = ?
             ORDER BY pr.`year` DESC, pr.`round` ASC, pr.winner_tid ASC, pr.loser_tid ASC",
            "ss",
            $teamName,
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgencyRoster()
     * @return list<PlayerRow>
     */
    public function getFreeAgencyRoster(int $teamid): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM `ibl_plr`
            WHERE teamid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC, pid ASC",
            "i",
            $teamid
        );
    }

    /**
     * @see TeamRepositoryInterface::getRosterUnderContract()
     * @return list<PlayerRow>
     */
    public function getRosterUnderContract(int $teamid): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM `ibl_plr`
            WHERE teamid = ?
              AND retired = 0
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC, pid ASC",
            "i",
            $teamid
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgents()
     * @return list<PlayerRow>
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): array
    {
        if ($includeFreeAgencyActive) {
            /** @var list<PlayerRow> */
            return $this->fetchAll(
                "SELECT * FROM `ibl_plr` WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC, pid ASC"
            );
        }
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_plr` WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC, pid ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getEntireLeagueRoster()
     * @return list<PlayerRow>
     */
    public function getEntireLeagueRoster(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_plr` WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC, pid ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getHistoricalRoster()
     * @return list<HistRow>
     */
    public function getHistoricalRoster(int $teamid, string $year): array
    {
        /** @var list<HistRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_hist` WHERE teamid = ? AND year = ? ORDER BY name ASC, pid ASC",
            "is",
            $teamid,
            $year
        );
    }

    /**
     * @see TeamRepositoryInterface::getFranchiseSeasons()
     * @return list<FranchiseSeasonRow>
     */
    public function getFranchiseSeasons(int $franchiseId): array
    {
        /** @var list<FranchiseSeasonRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_franchise_seasons` WHERE franchise_id = ? ORDER BY season_year ASC, id ASC",
            "i",
            $franchiseId
        );
    }

    /**
     * @see TeamRepositoryInterface::getAllTeams()
     * @return list<array{teamid: int, team_name: string}>
     */
    public function getAllTeams(): array
    {
        /** @var list<array{teamid: int, team_name: string}> */
        return $this->fetchAllRealTeams(\TeamOrderBy::TeamName);
    }
}
