<?php

declare(strict_types=1);

namespace RecordHolders;

use League\League;
use League\LeagueContext;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * Queries for player-level records: quadruple-doubles, all-star appearances,
 * single-game bests, and season-average bests.
 *
 * @phpstan-import-type PlayerSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayerSeasonRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type QuadrupleDoubleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type AllStarRecord from RecordHoldersRepositoryInterface
 */
final class PlayerRecordRepository extends \BaseMysqliRepository
{
    private const SEASON_YEAR_EXPRESSION = 'bs.season_year';

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Fetch all quadruple-double performances in regular-season, playoff, and HEAT games.
     *
     * @return list<QuadrupleDoubleRecord>
     */
    public function getQuadrupleDoubles(): array
    {
        $query = "WITH _game_of_day AS " . $this->gameOfThatDaySubquery() . ",
            _qd_candidates AS (
                -- Provably-complete candidate prune: every quad-double has >= 2 of
                -- {ast,stl,blk} >= 10, so this UNION is a superset of qualifying rows.
                -- game_type IN (0,1,2,3) = the full domain of the generated column
                -- (a tautology), which lets each leg use its (game_type,<stat>) index
                -- as a range scan instead of a full table scan. UNION (not UNION ALL)
                -- dedups ids so the PK join below cannot fan rows out.
                SELECT id FROM `ibl_box_scores` WHERE game_type IN (0,1,2,3) AND game_ast >= 10
                UNION
                SELECT id FROM `ibl_box_scores` WHERE game_type IN (0,1,2,3) AND game_stl >= 10
                UNION
                SELECT id FROM `ibl_box_scores` WHERE game_type IN (0,1,2,3) AND game_blk >= 10
            )
            SELECT
                bs.pid,
                p.name,
                h.teamid AS teamid,
                h.team AS team_name,
                bs.game_date AS `date`,
                COALESCE(sch.box_id, 0) AS box_id,
                COALESCE(bst.game_of_that_day, 0) AS game_of_that_day,
                CASE WHEN h.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,
                opp.team_name AS opp_team_name,
                bs.calc_points AS points,
                bs.calc_rebounds AS rebounds,
                bs.game_ast AS assists,
                bs.game_stl AS steals,
                bs.game_blk AS blocks
            FROM `ibl_box_scores` bs
            JOIN _qd_candidates c ON c.id = bs.id
            JOIN `ibl_plr` p ON p.pid = bs.pid
            JOIN `ibl_hist` h ON h.pid = bs.pid AND h.year = (" . self::SEASON_YEAR_EXPRESSION . ")
            LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date
                AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
            LEFT JOIN _game_of_day bst ON bst.game_date = bs.game_date
                AND bst.visitor_teamid = bs.visitor_teamid AND bst.home_teamid = bs.home_teamid
            LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                WHEN h.teamid = bs.visitor_teamid THEN bs.home_teamid
                ELSE bs.visitor_teamid END
            WHERE (
                (CASE WHEN bs.calc_points >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.calc_rebounds >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.game_ast >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.game_stl >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.game_blk >= 10 THEN 1 ELSE 0 END)
            ) >= 4
                AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY bs.game_date ASC";

        $rows = $this->fetchAll($query);

        /** @var list<QuadrupleDoubleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'teamid' => $row['teamid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'box_id' => $row['box_id'],
                'game_of_that_day' => $row['game_of_that_day'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'points' => $row['points'],
                'rebounds' => $row['rebounds'],
                'assists' => $row['assists'],
                'steals' => $row['steals'],
                'blocks' => $row['blocks'],
            ];
        }

        return $records;
    }

    /**
     * Fetch top-N players by all-star game appearances.
     *
     * @return list<AllStarRecord>
     */
    public function getMostAllStarAppearances(): array
    {
        $query = "SELECT a.name, h.pid, COUNT(*) AS appearances
            FROM `ibl_awards` a
            LEFT JOIN (SELECT DISTINCT pid, name FROM `ibl_hist`) h ON h.name = a.name
            WHERE a.award LIKE '%Conference All-Star'
            GROUP BY a.name, h.pid
            ORDER BY appearances DESC, a.name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<AllStarRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{name: string, pid: int|null, appearances: int} $row */
            $records[] = [
                'name' => $row['name'],
                'pid' => $row['pid'] !== null ? (int) $row['pid'] : null,
                'appearances' => $row['appearances'],
            ];
        }

        return $records;
    }

    /**
     * Fetch top-5 single-game records for each stat expression in one batched query.
     *
     * @param array<string, string> $statExpressions
     * @return array<string, list<PlayerSingleGameRecord>>
     */
    public function getTopPlayerSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        if ($statExpressions === []) {
            return [];
        }

        $unions = [];
        foreach ($statExpressions as $label => $expression) {
            $safeLabel = str_replace("'", "''", $label);
            $unions[] = "(SELECT
                    '" . $safeLabel . "' AS stat_type,
                    cand.pid,
                    p.name,
                    h.teamid AS teamid,
                    h.team AS team_name,
                    cand.game_date AS `date`,
                    COALESCE(sch.box_id, 0) AS box_id,
                    COALESCE(bst.game_of_that_day, 0) AS game_of_that_day,
                    CASE WHEN h.teamid = cand.visitor_teamid THEN cand.home_teamid ELSE cand.visitor_teamid END AS oppTid,
                    opp.team_name AS opp_team_name,
                    cand.value
                FROM (
                    SELECT
                        bs.pid,
                        bs.game_date,
                        bs.visitor_teamid,
                        bs.home_teamid,
                        (" . self::SEASON_YEAR_EXPRESSION . ") AS season_year,
                        " . $expression . " AS value
                    FROM `ibl_box_scores` bs
                    WHERE " . $dateFilter . "
                        AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                        AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                    ORDER BY " . $expression . " DESC
                    LIMIT 500
                ) cand
                JOIN `ibl_plr` p ON p.pid = cand.pid
                JOIN `ibl_hist` h ON h.pid = cand.pid AND h.year = cand.season_year
                LEFT JOIN `ibl_schedule` sch ON sch.game_date = cand.game_date
                    AND sch.visitor_teamid = cand.visitor_teamid AND sch.home_teamid = cand.home_teamid
                LEFT JOIN _game_of_day bst ON bst.game_date = cand.game_date
                    AND bst.visitor_teamid = cand.visitor_teamid AND bst.home_teamid = cand.home_teamid
                LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                    WHEN h.teamid = cand.visitor_teamid THEN cand.home_teamid
                    ELSE cand.visitor_teamid END
                ORDER BY cand.value DESC, cand.game_date ASC
                LIMIT 5)";
        }

        // CTE materializes game_of_that_day lookup once instead of per UNION ALL branch
        $cte = "WITH _game_of_day AS " . $this->gameOfThatDaySubquery() . "\n";
        $query = $cte . implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<PlayerSingleGameRecord>> $results */
        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'teamid' => $row['teamid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'box_id' => $row['box_id'],
                'game_of_that_day' => $row['game_of_that_day'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $results;
    }

    /**
     * Fetch top-5 season average records for each stat column in one batched query.
     *
     * @param array<string, array{statColumn: string, gamesColumn: string}> $statColumns
     * @return array<string, list<PlayerSeasonRecord>>
     */
    public function getTopSeasonAverageBatch(array $statColumns, int $minGames = 50): array
    {
        if ($statColumns === []) {
            return [];
        }

        $unions = [];
        foreach ($statColumns as $label => $columns) {
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $columns['statColumn']);
            $safeGames = preg_replace('/[^a-zA-Z0-9_]/', '', $columns['gamesColumn']);
            if ($safeColumn === null || $safeColumn === '' || $safeGames === null || $safeGames === '') {
                continue;
            }
            $safeLabel = str_replace("'", "''", $label);
            $unions[] = "(SELECT
                    '" . $safeLabel . "' AS stat_type,
                    h.pid,
                    h.name,
                    h.teamid,
                    h.team,
                    h.year,
                    ROUND(h." . $safeColumn . " / h." . $safeGames . ", 1) AS value
                FROM `ibl_hist` h
                WHERE h." . $safeGames . " >= " . $minGames . "
                    AND h.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                ORDER BY value DESC
                LIMIT 5)";
        }

        if ($unions === []) {
            return [];
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<PlayerSeasonRecord>> $results */
        $results = [];
        foreach (array_keys($statColumns) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, pid: int, name: string, teamid: int, team: string, year: int, value: float} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'teamid' => $row['teamid'],
                'team' => $row['team'],
                'year' => $row['year'],
                'value' => $row['value'],
            ];
        }

        return $results;
    }
}
