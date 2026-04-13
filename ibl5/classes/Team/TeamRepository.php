<?php

declare(strict_types=1);

namespace Team;

use League\LeagueContext;
use Team\Contracts\TeamRepositoryInterface;

/**
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
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
class TeamRepository extends \BaseMysqliRepository implements TeamRepositoryInterface
{
    private string $teamInfoTable;
    private string $standingsTable;
    private string $powerTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->standingsTable = $this->resolveTable('ibl_standings');
        $this->powerTable = $this->resolveTable('ibl_power');
    }

    /**
     * @see TeamRepositoryInterface::getTeam()
     * @return TeamInfoRow|null
     */
    public function getTeam(int $teamID): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM {$this->teamInfoTable} WHERE teamid = ?",
            "i",
            $teamID
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
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.tid = p.TeamID
            JOIN {$this->teamInfoTable} t ON s.tid = t.teamid
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
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.tid = p.TeamID
            WHERE s.division = ?
            ORDER BY s.divGB ASC",
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
            "SELECT s.tid, s.team_name, s.leagueRecord, s.wins, s.losses, s.pct,
                s.conference, s.division, s.confRecord, s.divRecord, s.divGB,
                s.homeRecord, s.awayRecord, s.gamesUnplayed,
                p.ranking, p.last_win, p.last_loss, p.streak_type, p.streak,
                p.sos, p.remaining_sos
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.tid = p.TeamID
            WHERE s.conference = ?
            ORDER BY s.confGB ASC",
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
            "SELECT * FROM ibl_banners WHERE currentname = ? ORDER BY year ASC",
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
            "SELECT * FROM ibl_gm_tenures WHERE franchise_id = ? ORDER BY start_season_year ASC",
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
            "SELECT * FROM ibl_gm_awards WHERE name = ? ORDER BY year ASC",
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
            "sss",
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
        return $this->fetchAll(
            self::buildWinLossQuery(1),
            "sss",
            $teamName,
            $teamName,
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getHEATHistory()
     * @return list<HEATWinLossRow>
     */
    public function getHEATHistory(string $teamName): array
    {
        /** @var list<HEATWinLossRow> */
        return $this->fetchAll(
            self::buildHeatWinLossQuery(),
            "sss",
            $teamName,
            $teamName,
            $teamName
        );
    }

    /**
     * Build inlined regular-season win/loss query with team filter pushed into CTE.
     *
     * Replaces SELECT from ibl_team_win_loss view which materializes ALL games
     * before filtering by team.
     */
    private static function buildWinLossQuery(int $gameType): string
    {
        return "WITH unique_games AS (
            SELECT Date, visitorTeamID, homeTeamID, gameOfThatDay,
                (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
                 + COALESCE(visitorOTpoints, 0)) AS visitor_total,
                (homeQ1points + homeQ2points + homeQ3points + homeQ4points
                 + COALESCE(homeOTpoints, 0)) AS home_total
            FROM ibl_box_scores_teams
            WHERE game_type = {$gameType}
                AND (visitorTeamID = (SELECT teamid FROM ibl_team_info WHERE team_name = ?)
                     OR homeTeamID = (SELECT teamid FROM ibl_team_info WHERE team_name = ?))
            GROUP BY Date, visitorTeamID, homeTeamID, gameOfThatDay
        ),
        team_games AS (
            SELECT visitorTeamID AS team_id, Date,
                   IF(visitor_total > home_total, 1, 0) AS win,
                   IF(visitor_total < home_total, 1, 0) AS loss
            FROM unique_games
            UNION ALL
            SELECT homeTeamID AS team_id, Date,
                   IF(home_total > visitor_total, 1, 0) AS win,
                   IF(home_total < visitor_total, 1, 0) AS loss
            FROM unique_games
        )
        SELECT
            CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1
                 ELSE YEAR(tg.Date) END AS year,
            ti.team_name AS currentname,
            COALESCE(fs.team_name, ti.team_name) AS namethatyear,
            CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
            CAST(SUM(tg.loss) AS UNSIGNED) AS losses
        FROM team_games tg
        JOIN ibl_team_info ti ON ti.teamid = tg.team_id
        LEFT JOIN ibl_franchise_seasons fs
            ON fs.franchise_id = tg.team_id
            AND fs.season_ending_year = (
                CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1
                     ELSE YEAR(tg.Date) END
            )
        WHERE tg.team_id = (SELECT teamid FROM ibl_team_info WHERE team_name = ?)
        GROUP BY
            tg.team_id,
            CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1 ELSE YEAR(tg.Date) END,
            ti.team_name,
            COALESCE(fs.team_name, ti.team_name)
        ORDER BY year DESC";
    }

    /**
     * Build inlined HEAT win/loss query with team filter pushed into CTE.
     *
     * Replaces SELECT from ibl_heat_win_loss view.
     */
    private static function buildHeatWinLossQuery(): string
    {
        return "WITH unique_games AS (
            SELECT Date, visitorTeamID, homeTeamID, gameOfThatDay,
                (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
                 + COALESCE(visitorOTpoints, 0)) AS visitor_total,
                (homeQ1points + homeQ2points + homeQ3points + homeQ4points
                 + COALESCE(homeOTpoints, 0)) AS home_total
            FROM ibl_box_scores_teams
            WHERE game_type = 3
                AND YEAR(Date) < 9000
                AND (visitorTeamID = (SELECT teamid FROM ibl_team_info WHERE team_name = ?)
                     OR homeTeamID = (SELECT teamid FROM ibl_team_info WHERE team_name = ?))
            GROUP BY Date, visitorTeamID, homeTeamID, gameOfThatDay
        ),
        team_games AS (
            SELECT visitorTeamID AS team_id, Date,
                   IF(visitor_total > home_total, 1, 0) AS win,
                   IF(visitor_total < home_total, 1, 0) AS loss
            FROM unique_games
            UNION ALL
            SELECT homeTeamID AS team_id, Date,
                   IF(home_total > visitor_total, 1, 0) AS win,
                   IF(home_total < visitor_total, 1, 0) AS loss
            FROM unique_games
        )
        SELECT
            YEAR(tg.Date) AS year,
            ti.team_name AS currentname,
            COALESCE(fs.team_name, ti.team_name) AS namethatyear,
            CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
            CAST(SUM(tg.loss) AS UNSIGNED) AS losses
        FROM team_games tg
        JOIN ibl_team_info ti ON ti.teamid = tg.team_id
        LEFT JOIN ibl_franchise_seasons fs
            ON fs.franchise_id = tg.team_id
            AND fs.season_ending_year = (YEAR(tg.Date) + 1)
        WHERE tg.team_id = (SELECT teamid FROM ibl_team_info WHERE team_name = ?)
        GROUP BY
            tg.team_id,
            YEAR(tg.Date),
            ti.team_name,
            COALESCE(fs.team_name, ti.team_name)
        ORDER BY year DESC";
    }

    /**
     * Build inlined team accomplishments query with name predicate pushed into each UNION branch.
     *
     * Uses window functions instead of correlated subqueries to avoid
     * re-materializing vw_playoff_series_results per row.
     */
    private static function buildTeamAccomplishmentsQuery(): string
    {
        return "SELECT year, name, Award, ID
            FROM ibl_team_awards
            WHERE name = ?

            UNION ALL

            SELECT ranked.year, ranked.name, 'IBL Champions' AS Award, 0 AS ID
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

            SELECT hc.year, ti.team_name AS name, 'IBL HEAT Champions' AS Award, 0 AS ID
            FROM (
                SELECT
                    YEAR(bst.Date) AS year,
                    CASE
                        WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points
                              + COALESCE(bst.homeOTpoints, 0))
                           > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points
                              + COALESCE(bst.visitorOTpoints, 0))
                        THEN bst.homeTeamID
                        ELSE bst.visitorTeamID
                    END AS winner_tid,
                    ROW_NUMBER() OVER (
                        PARTITION BY YEAR(bst.Date)
                        ORDER BY bst.Date DESC, bst.gameOfThatDay ASC
                    ) AS rn
                FROM ibl_box_scores_teams bst
                WHERE bst.game_type = 3
            ) hc
            JOIN ibl_team_info ti ON ti.teamid = hc.winner_tid
            WHERE hc.rn = 1 AND ti.team_name = ?

            ORDER BY year DESC";
    }

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     * @return list<PlayoffResultRow>
     */
    public function getPlayoffResults(): array
    {
        /** @var list<PlayoffResultRow> */
        return $this->fetchAll(
            "SELECT pr.year, pr.round, pr.winner, pr.loser, pr.winner_games, pr.loser_games,
                    COALESCE(wfs.team_name, pr.winner) AS winner_name_that_year,
                    COALESCE(lfs.team_name, pr.loser) AS loser_name_that_year
             FROM vw_playoff_series_results pr
             LEFT JOIN ibl_franchise_seasons wfs ON wfs.franchise_id = pr.winner_tid AND wfs.season_ending_year = pr.year
             LEFT JOIN ibl_franchise_seasons lfs ON lfs.franchise_id = pr.loser_tid AND lfs.season_ending_year = pr.year
             ORDER BY pr.year DESC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgencyRoster()
     * @return list<PlayerRow>
     */
    public function getFreeAgencyRoster(int $teamID): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getRosterUnderContract()
     * @return list<PlayerRow>
     */
    public function getRosterUnderContract(int $teamID): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
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
                "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC"
            );
        }
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC"
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
            "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getHistoricalRoster()
     * @return list<HistRow>
     */
    public function getHistoricalRoster(int $teamID, string $year): array
    {
        /** @var list<HistRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE teamid = ? AND year = ? ORDER BY name ASC",
            "is",
            $teamID,
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
            "SELECT * FROM ibl_franchise_seasons WHERE franchise_id = ? ORDER BY season_year ASC",
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
        return $this->fetchAllRealTeams('team_name ASC');
    }
}
