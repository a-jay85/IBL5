<?php

declare(strict_types=1);

namespace SeasonArchive;

use BaseMysqliRepository;
use League\League;
use League\LeagueContext;
use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;

/**
 * SeasonArchiveRepository - Data access layer for season archive
 *
 * Retrieves awards, playoff results, team awards, HEAT standings,
 * GM history, and team colors from the database.
 *
 * @phpstan-import-type AwardRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type PlayoffRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type TeamAwardRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmAwardWithTeamRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmTenureWithTeamRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 * @phpstan-import-type HeatWinLossRow from \SeasonArchive\Contracts\SeasonArchiveRepositoryInterface
 *
 * @see SeasonArchiveRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class SeasonArchiveRepository extends BaseMysqliRepository implements SeasonArchiveRepositoryInterface
{
    private string $teamInfoTable;
    private string $standingsTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
        $this->standingsTable = $this->resolveTable('ibl_standings');
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAllSeasonYears()
     */
    public function getAllSeasonYears(): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT year FROM ibl_awards WHERE year > 1 ORDER BY year ASC"
        );

        $years = [];
        foreach ($rows as $row) {
            /** @var array{year: int} $row */
            $years[] = $row['year'];
        }

        return $years;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAwardsByYear()
     */
    public function getAwardsByYear(int $year): array
    {
        /** @var list<AwardRow> */
        return $this->fetchAll(
            "SELECT year, award, name, table_id FROM ibl_awards WHERE year = ? ORDER BY award ASC, table_id ASC",
            "i",
            $year
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getPlayoffResultsByYear()
     */
    public function getPlayoffResultsByYear(int $year): array
    {
        /** @var list<PlayoffRow> */
        return $this->fetchAll(
            "SELECT year, round, winner, loser, winner_games, loser_games FROM vw_playoff_series_results WHERE year = ? ORDER BY round ASC, winner ASC",
            "i",
            $year
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamAwardsByYear()
     */
    public function getTeamAwardsByYear(int $year): array
    {
        /** @var list<TeamAwardRow> */
        return $this->fetchAll(
            self::buildTeamAwardsByYearQuery(),
            "iii",
            $year,
            $year,
            $year
        );
    }

    /**
     * Inlined team awards query with year predicate pushed into each UNION branch.
     *
     * Uses window functions instead of correlated subqueries.
     */
    private static function buildTeamAwardsByYearQuery(): string
    {
        return "SELECT year, name, award, id
            FROM ibl_team_awards
            WHERE year = ?

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
                WHERE psr.year = ?
            ) ranked
            WHERE ranked.round = ranked.max_round AND ranked.series_in_round = 1

            UNION ALL

            SELECT hc.year, ti.team_name AS name, 'IBL HEAT Champions' AS award, 0 AS id
            FROM (
                SELECT
                    YEAR(bst.Date) AS year,
                    CASE
                        WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points
                              + COALESCE(bst.homeOTpoints, 0))
                           > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points
                              + COALESCE(bst.visitorOTpoints, 0))
                        THEN bst.home_teamid
                        ELSE bst.visitor_teamid
                    END AS winner_tid,
                    ROW_NUMBER() OVER (
                        PARTITION BY YEAR(bst.Date)
                        ORDER BY bst.Date DESC, bst.gameOfThatDay ASC
                    ) AS rn
                FROM ibl_box_scores_teams bst
                WHERE bst.game_type = 3 AND YEAR(bst.Date) = ?
            ) hc
            JOIN ibl_team_info ti ON ti.teamid = hc.winner_tid
            WHERE hc.rn = 1

            ORDER BY " . self::AWARD_HIERARCHY_CASE . ", award ASC, name ASC";
    }

    /**
     * Hierarchical award ordering used by team-award queries.
     *
     * Orders awards from hardest to easiest to win so season-archive displays are
     * deterministic (avoids flaky e2e tests when a year has multiple awards):
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
     * @see SeasonArchiveRepositoryInterface::getAllGmAwardsWithTeams()
     */
    public function getAllGmAwardsWithTeams(): array
    {
        /** @var list<GmAwardWithTeamRow> */
        return $this->fetchAll(
            "SELECT ga.year, ga.award, ga.name AS gm_display_name, ti.team_name, ga.table_id
            FROM ibl_gm_awards ga
            JOIN ibl_gm_tenures gt ON ga.name = gt.gm_display_name
                AND ga.year >= gt.start_season_year
                AND (gt.end_season_year IS NULL OR ga.year <= gt.end_season_year)
            JOIN {$this->teamInfoTable} ti ON gt.franchise_id = ti.teamid
            ORDER BY ga.year ASC"
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getAllGmTenuresWithTeams()
     */
    public function getAllGmTenuresWithTeams(): array
    {
        /** @var list<GmTenureWithTeamRow> */
        return $this->fetchAll(
            "SELECT gt.gm_display_name, gt.start_season_year, gt.end_season_year, ti.team_name
            FROM ibl_gm_tenures gt
            JOIN {$this->teamInfoTable} ti ON gt.franchise_id = ti.teamid
            ORDER BY gt.start_season_year ASC"
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getHeatWinLossByYear()
     */
    public function getHeatWinLossByYear(int $heatYear): array
    {
        /** @var list<HeatWinLossRow> */
        return $this->fetchAll(
            "SELECT hwl.year, hwl.currentname, hwl.namethatyear, hwl.wins, hwl.losses
            FROM ibl_heat_win_loss hwl
            JOIN {$this->teamInfoTable} ti ON ti.team_name = hwl.currentname
            WHERE hwl.year = ?
                AND ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY hwl.wins DESC, hwl.losses ASC",
            "i",
            $heatYear
        );
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamColors()
     */
    public function getTeamColors(): array
    {
        $rows = $this->fetchAllRealTeams('teamid ASC');

        $colors = [];
        foreach ($rows as $row) {
            /** @var array{teamid: int, team_name: string, color1: string, color2: string} $row */
            $colors[$row['team_name']] = [
                'color1' => $row['color1'],
                'color2' => $row['color2'],
                'teamid' => $row['teamid'],
            ];
        }

        return $colors;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getPlayerIdsByNames()
     */
    public function getPlayerIdsByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));

        /** @var \mysqli $db */
        $db = $this->db;
        $stmt = $db->prepare("SELECT pid, name FROM ibl_plr WHERE name IN ({$placeholders})");
        if ($stmt === false) {
            return [];
        }

        $stmt->execute($names);
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return [];
        }

        /** @var array<string, int> $map */
        $map = [];
        while (true) {
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $map[(string) $row['name']] = (int) $row['pid'];
        }
        $stmt->close();

        return $map;
    }

    /**
     * @see SeasonArchiveRepositoryInterface::getTeamConferences()
     */
    public function getTeamConferences(): array
    {
        $rows = $this->fetchAll(
            "SELECT team_name, conference FROM {$this->standingsTable} WHERE conference <> ''"
        );

        $map = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, conference: string} $row */
            $map[$row['team_name']] = $row['conference'];
        }

        return $map;
    }
}
