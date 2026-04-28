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
    public function getTeam(int $teamid): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM {$this->teamInfoTable} WHERE teamid = ?",
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
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.teamid = p.teamid
            JOIN {$this->teamInfoTable} t ON s.teamid = t.teamid
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
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.teamid = p.teamid
            WHERE s.division = ?
            ORDER BY s.div_gb ASC",
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
            FROM {$this->standingsTable} s
            JOIN {$this->powerTable} p ON s.teamid = p.teamid
            WHERE s.conference = ?
            ORDER BY s.conf_gb ASC",
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
            "SELECT `year`, currentname, namethatyear, wins, losses
             FROM ibl_team_season_records
             WHERE currentname = ? AND game_type = 1
             ORDER BY `year` DESC",
            "s",
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
            "SELECT `year`, currentname, namethatyear, wins, losses
             FROM ibl_team_season_records
             WHERE currentname = ? AND game_type = 3
             ORDER BY `year` DESC",
            "s",
            $teamName
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
            FROM ibl_team_awards
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

            SELECT hc.year, ti.team_name AS name, 'IBL HEAT Champions' AS award, 0 AS id
            FROM (
                SELECT
                    YEAR(bst.game_date) AS year,
                    CASE
                        WHEN (bst.home_q1_points + bst.home_q2_points + bst.home_q3_points + bst.home_q4_points
                              + COALESCE(bst.home_ot_points, 0))
                           > (bst.visitor_q1_points + bst.visitor_q2_points + bst.visitor_q3_points + bst.visitor_q4_points
                              + COALESCE(bst.visitor_ot_points, 0))
                        THEN bst.home_teamid
                        ELSE bst.visitor_teamid
                    END AS winner_tid,
                    ROW_NUMBER() OVER (
                        PARTITION BY YEAR(bst.game_date)
                        ORDER BY bst.game_date DESC, bst.game_of_that_day ASC
                    ) AS rn
                FROM ibl_box_scores_teams bst
                WHERE bst.game_type = 3
            ) hc
            JOIN ibl_team_info ti ON ti.teamid = hc.winner_tid
            WHERE hc.rn = 1 AND ti.team_name = ?

            ORDER BY year DESC, " . self::AWARD_HIERARCHY_CASE . ", award ASC";
    }

    /**
     * Hierarchical award ordering used by accomplishments queries.
     *
     * Orders awards from hardest to easiest to win so team-history displays are
     * deterministic (avoids flaky e2e tests when multiple awards share a year):
     * IBL Champions → IBL HEAT Champions → Conference Champions (alpha) →
     * Division Champions (alpha) → IBL Draft Lottery Winners → everything else.
     */
    private const AWARD_HIERARCHY_CASE = "CASE award
                WHEN 'IBL Champions' THEN 1
                WHEN 'IBL HEAT Champions' THEN 2
                WHEN 'Eastern Conference Champions' THEN 3
                WHEN 'Western Conference Champions' THEN 4
                WHEN 'Atlantic Division Champions' THEN 5
                WHEN 'Central Division Champions' THEN 6
                WHEN 'Midwest Division Champions' THEN 7
                WHEN 'Pacific Division Champions' THEN 8
                WHEN 'IBL Draft Lottery Winners' THEN 9
                ELSE 10
            END";

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     * @return list<PlayoffResultRow>
     */
    public function getPlayoffResults(string $teamName): array
    {
        /** @var list<PlayoffResultRow> */
        return $this->fetchAll(
            "SELECT pr.`year`, pr.`round`, pr.winner, pr.loser, pr.winner_games, pr.loser_games,
                    COALESCE(wfs.team_name, pr.winner) AS winner_name_that_year,
                    COALESCE(lfs.team_name, pr.loser) AS loser_name_that_year
             FROM ibl_playoff_series_results pr
             LEFT JOIN ibl_franchise_seasons wfs ON wfs.franchise_id = pr.winner_tid AND wfs.season_ending_year = pr.`year`
             LEFT JOIN ibl_franchise_seasons lfs ON lfs.franchise_id = pr.loser_tid AND lfs.season_ending_year = pr.`year`
             WHERE pr.winner = ? OR pr.loser = ?
             ORDER BY pr.`year` DESC",
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
            FROM ibl_plr
            WHERE teamid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
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
            FROM ibl_plr
            WHERE teamid = ?
              AND retired = 0
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
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
    public function getHistoricalRoster(int $teamid, string $year): array
    {
        /** @var list<HistRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE teamid = ? AND year = ? ORDER BY name ASC",
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
