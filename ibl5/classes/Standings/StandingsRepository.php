<?php

declare(strict_types=1);

namespace Standings;

use League\League;
use League\LeagueContext;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsRepository - Data access layer for team standings
 *
 * Retrieves standings data from ibl_standings and ibl_power tables.
 * Supports both conference and division groupings.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type BulkStandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 * @phpstan-import-type SeriesRecordRow from StandingsRepositoryInterface
 *
 * @see StandingsRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class StandingsRepository extends \BaseMysqliRepository implements StandingsRepositoryInterface
{
    private string $standingsTable;
    private string $powerTable;
    private string $teamInfoTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->standingsTable = $this->resolveTable('ibl_standings');
        $this->powerTable = $this->resolveTable('ibl_power');
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
    }

    /**
     * Get grouping column names for a region type
     *
     * @param string $region Region name
     * @return array{grouping: string, gbColumn: string, magicNumberColumn: string}
     */
    private function getGroupingColumns(string $region): array
    {
        if (in_array($region, League::CONFERENCE_NAMES, true)) {
            return [
                'grouping' => 'conference',
                'gbColumn' => 'conf_gb',
                'magicNumberColumn' => 'conf_magic_number',
            ];
        }

        if (in_array($region, League::DIVISION_NAMES, true)) {
            return [
                'grouping' => 'division',
                'gbColumn' => 'div_gb',
                'magicNumberColumn' => 'div_magic_number',
            ];
        }

        throw new \InvalidArgumentException("Invalid region: {$region}");
    }

    /**
     * @see StandingsRepositoryInterface::getStandingsByRegion()
     *
     * @return list<StandingsRow>
     */
    public function getStandingsByRegion(string $region): array
    {
        $columns = $this->getGroupingColumns($region);

        $query = "SELECT
            s.teamid,
            s.team_name,
            s.league_record,
            s.pct,
            s.{$columns['gbColumn']} AS gamesBack,
            s.conf_record,
            s.div_record,
            s.home_record,
            s.away_record,
            s.games_unplayed,
            s.{$columns['magicNumberColumn']} AS magicNumber,
            s.clinched_conference,
            s.clinched_division,
            s.clinched_playoffs,
            s.clinched_league,
            s.wins,
            (s.home_wins + s.home_losses) AS homeGames,
            (s.away_wins + s.away_losses) AS awayGames,
            t.color1,
            t.color2
            FROM {$this->standingsTable} s
            JOIN {$this->teamInfoTable} t ON s.teamid = t.teamid
            WHERE s.{$columns['grouping']} = ?
            ORDER BY s.{$columns['gbColumn']} ASC,
                (COALESCE(s.clinched_league, 0) * 4
                 + COALESCE(s.clinched_conference, 0) * 3
                 + COALESCE(s.clinched_division, 0) * 2
                 + COALESCE(s.clinched_playoffs, 0)) DESC,
                s.wins DESC";

        /** @var list<StandingsRow> */
        return $this->fetchAll($query, "s", $region);
    }

    /**
     * @see StandingsRepositoryInterface::getAllStandings()
     *
     * @return list<BulkStandingsRow>
     */
    public function getAllStandings(): array
    {
        /** @var list<BulkStandingsRow> */
        return $this->fetchAll(
            "SELECT
                s.teamid, s.team_name, s.league_record, s.pct,
                s.conf_gb, s.div_gb,
                s.conf_record, s.div_record, s.home_record, s.away_record,
                s.games_unplayed,
                s.conf_magic_number, s.div_magic_number,
                s.clinched_conference, s.clinched_division, s.clinched_playoffs, s.clinched_league,
                s.wins,
                (s.home_wins + s.home_losses) AS homeGames,
                (s.away_wins + s.away_losses) AS awayGames,
                s.conference, s.division,
                t.color1, t.color2
            FROM {$this->standingsTable} s
            JOIN {$this->teamInfoTable} t ON s.teamid = t.teamid",
            ""
        );
    }

    /**
    * @see StandingsRepositoryInterface::getTeamStreakData()
     *
     * @return StreakRow|null
     */
    public function getTeamStreakData(int $teamId): ?array
    {
        /** @var StreakRow|null */
        return $this->fetchOne(
            "SELECT last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM {$this->powerTable} WHERE teamid = ?",
            "i",
            $teamId
        );
    }

    /**
     * @see StandingsRepositoryInterface::getTeamPythagoreanStats()
     *
     * @return PythagoreanStats|null
     */
    public function getTeamPythagoreanStats(int $teamId, int $seasonYear): ?array
    {
        /** @var array{off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int}|null $stats */
        $stats = $this->fetchOne(
            "SELECT
                tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm,
                tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm
            FROM (" . self::buildOffenseSubquery('bst.season_year = ? AND fs.franchise_id = ?') . ") tos
            JOIN (" . self::buildDefenseSubquery('my.season_year = ? AND fs.franchise_id = ?') . ") tds
                ON tos.teamid = tds.teamid AND tos.season_year = tds.season_year",
            "iiii",
            $seasonYear,
            $teamId,
            $seasonYear,
            $teamId
        );

        if ($stats === null) {
            return null;
        }

        return $this->calculatePythagoreanStats($stats);
    }

    /**
     * @see StandingsRepositoryInterface::getAllStreakData()
     *
     * @return array<int, StreakRow>
     */
    public function getAllStreakData(): array
    {
        /** @var list<array{teamid: int, last_win: int, last_loss: int, streak_type: string, streak: int, ranking: int, sos: float|string, remaining_sos: float|string, sos_rank: int, remaining_sos_rank: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid, last_win, last_loss, streak_type, streak, ranking, sos, remaining_sos, sos_rank, remaining_sos_rank FROM {$this->powerTable}",
            ""
        );

        /** @var array<int, StreakRow> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamid']] = [
                'last_win' => $row['last_win'],
                'last_loss' => $row['last_loss'],
                'streak_type' => $row['streak_type'],
                'streak' => $row['streak'],
                'ranking' => $row['ranking'],
                'sos' => $row['sos'],
                'remaining_sos' => $row['remaining_sos'],
                'sos_rank' => $row['sos_rank'],
                'remaining_sos_rank' => $row['remaining_sos_rank'],
            ];
        }

        return $result;
    }

    /**
     * @see StandingsRepositoryInterface::getAllPythagoreanStats()
     *
     * @return array<int, PythagoreanStats>
     */
    public function getAllPythagoreanStats(int $seasonYear): array
    {
        /** @var list<array{teamid: int, off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                tos.teamid,
                tos.fgm AS off_fgm, tos.ftm AS off_ftm, tos.tgm AS off_tgm,
                tds.fgm AS def_fgm, tds.ftm AS def_ftm, tds.tgm AS def_tgm
            FROM (" . self::buildOffenseSubquery('bst.season_year = ?') . ") tos
            JOIN (" . self::buildDefenseSubquery('my.season_year = ?') . ") tds
                ON tos.teamid = tds.teamid AND tos.season_year = tds.season_year",
            "ii",
            $seasonYear,
            $seasonYear
        );

        /** @var array<int, PythagoreanStats> $result */
        $result = [];
        foreach ($rows as $row) {
            $result[$row['teamid']] = $this->calculatePythagoreanStats($row);
        }

        return $result;
    }

    /**
     * @see StandingsRepositoryInterface::getSeriesRecords()
     *
     * @return list<SeriesRecordRow>
     */
    public function getSeriesRecords(): array
    {
        /** @var list<SeriesRecordRow> */
        return $this->fetchAll(
            "SELECT r.team_id AS self, r.opponent_id AS opponent,
                    SUM(r.is_win) AS wins, SUM(1 - r.is_win) AS losses
            FROM (
                SELECT bst.visitorTeamID AS team_id, bst.homeTeamID AS opponent_id,
                    CASE WHEN (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                              > (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type = 1
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type = 1
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                UNION ALL
                SELECT bst.homeTeamID AS team_id, bst.visitorTeamID AS opponent_id,
                    CASE WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
                              > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
                         THEN 1 ELSE 0 END AS is_win
                FROM ibl_box_scores_teams bst
                WHERE bst.id IN (
                    SELECT MIN(b2.id) FROM ibl_box_scores_teams b2
                    WHERE b2.game_type = 1
                    GROUP BY b2.Date, b2.gameOfThatDay, b2.visitorTeamID, b2.homeTeamID
                )
                AND bst.game_type = 1
                AND bst.visitorTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bst.homeTeamID BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ) r
            GROUP BY r.team_id, r.opponent_id
            ORDER BY r.team_id, r.opponent_id",
            ""
        );
    }

    /**
     * Calculate Pythagorean stats from raw shooting data
     *
     * @param array{off_fgm: int, off_ftm: int, off_tgm: int, def_fgm: int, def_ftm: int, def_tgm: int} $stats
     * @return PythagoreanStats
     */
    private function calculatePythagoreanStats(array $stats): array
    {
        $pointsScored = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['off_fgm'],
            $stats['off_ftm'],
            $stats['off_tgm']
        );

        $pointsAllowed = \BasketballStats\StatsFormatter::calculatePoints(
            $stats['def_fgm'],
            $stats['def_ftm'],
            $stats['def_tgm']
        );

        return [
            'pointsScored' => $pointsScored,
            'pointsAllowed' => $pointsAllowed,
        ];
    }

    /**
     * Build inlined offense stats subquery with filter pushed before GROUP BY.
     */
    private static function buildOffenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamid, fs.team_name AS name, bst.season_year,
            CAST(SUM(bst.game_2gm + bst.game_3gm) AS SIGNED) AS fgm,
            CAST(SUM(bst.game_ftm) AS SIGNED) AS ftm,
            CAST(SUM(bst.game_3gm) AS SIGNED) AS tgm
        FROM ibl_box_scores_teams bst
        JOIN ibl_franchise_seasons fs
            ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year
        WHERE bst.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, bst.season_year";
    }

    /**
     * Build inlined defense stats subquery with filter pushed before GROUP BY.
     */
    private static function buildDefenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamid, fs.team_name AS name, my.season_year,
            CAST(SUM(opp.game_2gm + opp.game_3gm) AS SIGNED) AS fgm,
            CAST(SUM(opp.game_ftm) AS SIGNED) AS ftm,
            CAST(SUM(opp.game_3gm) AS SIGNED) AS tgm
        FROM ibl_box_scores_teams my
        JOIN ibl_box_scores_teams opp
            ON my.game_date = opp.game_date
            AND my.visitor_teamid = opp.visitor_teamid
            AND my.home_teamid = opp.home_teamid
            AND my.game_of_that_day = opp.game_of_that_day
            AND my.name <> opp.name
        JOIN ibl_franchise_seasons fs
            ON fs.team_name = my.name AND fs.season_ending_year = my.season_year
        WHERE my.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, my.season_year";
    }
}
