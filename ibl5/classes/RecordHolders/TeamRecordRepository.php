<?php

declare(strict_types=1);

namespace RecordHolders;

use League\League;
use League\LeagueContext;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * Queries for team-level records: half scores, margin of victory, season records,
 * streaks, season starts, and team single-game bests.
 *
 * Both memoization caches are per-instance (per-request). The repository is
 * instantiated once per request in RecordHoldersRepository, so the caches
 * cannot return stale data; no invalidation hook is required.
 *
 * @phpstan-import-type TeamSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type TeamHalfRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type MarginRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonWinLossRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type StreakRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonStartRecord from RecordHoldersRepositoryInterface
 */
final class TeamRecordRepository extends \Database\BaseMysqliRepository
{
    /**
     * Per-request memoization of regular-season game rows.
     *
     * Loaded once and reused by all four streak/season-start computations within a
     * single request. The repository is instantiated per request (see
     * modules/RecordHolders/index.php) and never reused across requests, so this
     * cache cannot return stale data; no invalidation hook is required.
     *
     * @var list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}>|null
     */
    private ?array $regularSeasonGamesCache = null;

    /**
     * Per-request memoization of team ID → team name lookups (request-scoped, see above).
     *
     * @var array<int, string>
     */
    private array $teamNameCache = [];

    /**
     * (game_type, stat) index per RecordStatDefinitions::STATS expression on
     * ibl_box_scores_teams. Hinted only when the date filter is a DATE_FILTERS
     * value (a game_type equality), so the index prefix matches the filter.
     */
    private const STAT_INDEXES = [
        'bs.calc_points' => 'idx_gt_points',
        'bs.calc_rebounds' => 'idx_gt_rebounds',
        'bs.game_ast' => 'idx_gt_ast',
        'bs.game_stl' => 'idx_gt_stl',
        'bs.game_blk' => 'idx_gt_blk',
        'bs.game_tov' => 'idx_gt_tov',
        'bs.calc_fg_made' => 'idx_gt_fg_made',
        'bs.game_ftm' => 'idx_gt_ftm',
        'bs.game_3gm' => 'idx_gt_3gm',
    ];

    /** Rows per single-game record list; the cut is the value at this rank. */
    private const SINGLE_GAME_LIMIT = 5;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * Fetch top-5 first- or second-half scores for a given ordering.
     *
     * @return list<TeamHalfRecord>
     */
    public function getTopTeamHalfScore(string $half, string $order): array
    {
        $safeOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        if ($half === 'first') {
            // First half: Q1 + Q2 — determine which team based on visitor/home columns
            $expression = "(CASE WHEN t.teamid = bs.visitor_teamid
                THEN bs.visitor_q1_points + bs.visitor_q2_points
                ELSE bs.home_q1_points + bs.home_q2_points END)";
        } else {
            // Second half: Q3 + Q4 + OT
            $expression = "(CASE WHEN t.teamid = bs.visitor_teamid
                THEN bs.visitor_q3_points + bs.visitor_q4_points + COALESCE(bs.visitor_ot_points, 0)
                ELSE bs.home_q3_points + bs.home_q4_points + COALESCE(bs.home_ot_points, 0) END)";
        }

        $query = "SELECT
                t.teamid AS teamid,
                t.team_name,
                bs.game_date AS `date`,
                COALESCE(sch.box_id, 0) AS box_id,
                COALESCE(bs.game_of_that_day, 0) AS game_of_that_day,
                CASE WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,
                opp.team_name AS opp_team_name,
                " . $expression . " AS value
            FROM `ibl_box_scores_teams` bs
            JOIN `ibl_team_info` t ON t.team_name = bs.name
            LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date
                AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
            LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid
                ELSE bs.visitor_teamid END
            WHERE bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID
            // @phpstan-ignore ibl.orderByMissingTiebreaker ($safeOrder is a validated ASC/DESC constant; the full ORDER BY ends with the bs.id PK)
            . " ORDER BY value " . $safeOrder . ", bs.game_date ASC, bs.id ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<TeamHalfRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
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

        return $records;
    }

    /**
     * Fetch top-5 games by margin of victory within a date range.
     *
     * Each row in ibl_box_scores_teams already has both visitor and home quarter scores,
     * so we compute the margin from a single row grouped by game (no self-join needed).
     *
     * @return list<MarginRecord>
     */
    public function getLargestMarginOfVictory(string $dateFilter): array
    {
        $query = "WITH _game_of_day AS " . self::GAME_OF_THAT_DAY_SUBQUERY . "
            SELECT
                winner_t.teamid AS winner_tid,
                winner_t.team_name AS winner_name,
                loser_t.teamid AS loser_tid,
                loser_t.team_name AS loser_name,
                sub.game_date AS `date`,
                COALESCE(sch.box_id, 0) AS box_id,
                COALESCE(bst.game_of_that_day, 0) AS game_of_that_day,
                sub.margin
            FROM (
                SELECT
                    bs.game_date,
                    bs.visitor_teamid,
                    bs.home_teamid,
                    ABS(bs.visitorScore - bs.homeScore) AS margin,
                    CASE WHEN bs.visitorScore > bs.homeScore
                        THEN bs.visitor_teamid ELSE bs.home_teamid END AS winner_id,
                    CASE WHEN bs.visitorScore > bs.homeScore
                        THEN bs.home_teamid ELSE bs.visitor_teamid END AS loser_id
                FROM vw_team_total_score bs
                WHERE " . $dateFilter . "
                    AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                    AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                GROUP BY bs.game_date, bs.visitor_teamid, bs.home_teamid
            ) sub
            JOIN `ibl_team_info` winner_t ON winner_t.teamid = sub.winner_id
            JOIN `ibl_team_info` loser_t ON loser_t.teamid = sub.loser_id
            LEFT JOIN `ibl_schedule` sch ON sch.game_date = sub.game_date
                AND sch.visitor_teamid = sub.visitor_teamid AND sch.home_teamid = sub.home_teamid
            LEFT JOIN _game_of_day bst ON bst.game_date = sub.game_date
                AND bst.visitor_teamid = sub.visitor_teamid AND bst.home_teamid = sub.home_teamid
            ORDER BY sub.margin DESC, sub.game_date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<MarginRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, box_id: int, game_of_that_day: int, margin: int} $row */
            $records[] = [
                'winner_tid' => $row['winner_tid'],
                'winner_name' => $row['winner_name'],
                'loser_tid' => $row['loser_tid'],
                'loser_name' => $row['loser_name'],
                'date' => $row['date'],
                'box_id' => $row['box_id'],
                'game_of_that_day' => $row['game_of_that_day'],
                'margin' => $row['margin'],
            ];
        }

        return $records;
    }

    /**
     * Fetch top-5 best or worst season win-loss records.
     *
     * @return list<SeasonWinLossRecord>
     */
    public function getBestWorstSeasonRecord(string $order): array
    {
        $safeOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT
                r.currentname AS team_name,
                r.year,
                r.wins,
                r.losses
            FROM `ibl_team_season_records` r
            JOIN `ibl_team_info` ti ON ti.teamid = r.team_id
            WHERE r.game_type = 1
                AND ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND (r.wins + r.losses) > 0"
            // @phpstan-ignore ibl.orderByMissingTiebreaker ($safeOrder is a validated ASC/DESC constant; the full ORDER BY ends with (year, teamid), unique per ibl_team_season_records row at game_type = 1 because the PK is (team_id, year, game_type))
            . " ORDER BY (r.wins / (r.wins + r.losses)) " . $safeOrder . ",
                r.wins " . $safeOrder . ", r.year ASC, ti.teamid ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<SeasonWinLossRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, year: int, wins: int, losses: int} $row */
            $records[] = [
                'team_name' => $row['team_name'],
                'year' => $row['year'],
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ];
        }

        return $records;
    }

    /**
     * Fetch top-5 team single-game records for each stat expression in one batched query.
     *
     * @param array<string, array{expression: string, order: string}> $statExpressions
     * @return array<string, list<TeamSingleGameRecord>>
     */
    public function getTopTeamSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        if ($statExpressions === []) {
            return [];
        }

        $thresholds = $this->fetchSingleGameThresholds($statExpressions, $dateFilter);
        $unions = [];
        $params = [];
        foreach ($statExpressions as $label => $config) {
            $branch = self::buildSingleGameBranch($label, $config, $dateFilter, $thresholds[$label] ?? null);
            $unions[] = $branch['sql'];
            array_push($params, ...$branch['params']);
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query, str_repeat('i', count($params)), ...$params);

        /** @var array<string, list<TeamSingleGameRecord>> $results */
        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        // MySQL promotes a UNION ALL column to DECIMAL when the branches alias
        // source columns of differing integer widths (here `calc_points`
        // smallint unsigned vs `game_ast` int), and mysqlnd hands DECIMAL back
        // as a PHP string. The merged type therefore depends on which stats are
        // in the batch, so cast here rather than trusting the driver's type.
        foreach ($rows as $row) {
            /** @var array{stat_type: string, teamid: int|string, team_name: string, date: string, box_id: int|string, game_of_that_day: int|string, oppTid: int|string, opp_team_name: string, value: int|string} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'teamid' => (int) $row['teamid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'box_id' => (int) $row['box_id'],
                'game_of_that_day' => (int) $row['game_of_that_day'],
                'oppTid' => (int) $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => (int) $row['value'],
            ];
        }

        return $results;
    }

    /**
     * Value at rank SINGLE_GAME_LIMIT of each stat's own order, one round trip
     * for the batch. Null when fewer rows match or that row's value is NULL;
     * the branch then skips the cut and runs the uncut query. The JOIN and
     * team-id filters match the record branch, because rows they drop would
     * otherwise raise the cut above a real record. The branch's LEFT JOINs can
     * only add rows, which never lowers the true rank-5 value below this one.
     *
     * @param array<string, array{expression: string, order: string}> $statExpressions
     * @return array<string, int|null>
     */
    private function fetchSingleGameThresholds(array $statExpressions, string $dateFilter): array
    {
        $branches = [];
        foreach ($statExpressions as $label => $config) {
            $safeLabel = str_replace("'", "''", $label);
            $safeOrder = $config['order'] === 'ASC' ? 'ASC' : 'DESC';
            $expression = $config['expression'];
            $branches[] = "(SELECT '" . $safeLabel . "' AS stat_type, (
                    SELECT " . $expression . "
                    " . self::singleGameSource(self::statIndex($expression, $dateFilter)) . "
                    " . self::singleGameWhere($dateFilter) . "
                    ORDER BY " . $expression . " " . $safeOrder . "
                    LIMIT 1 OFFSET " . (self::SINGLE_GAME_LIMIT - 1) . "
                ) AS threshold)";
        }

        $thresholds = [];
        foreach ($this->fetchAll(implode("\nUNION ALL\n", $branches)) as $row) {
            /** @var array{stat_type: string, threshold?: int|string|null} $row */
            $value = $row['threshold'] ?? null;
            $thresholds[$row['stat_type']] = $value === null ? null : (int) $value;
        }

        return $thresholds;
    }

    /**
     * One UNION ALL branch. A non-null threshold adds the cut; `>=` / `<=`
     * keep every row tied at the cut, so the ORDER BY tiebreak sees the same
     * leading rows as the uncut query. ASC also keeps NULL values, which
     * MariaDB sorts first in ASC order.
     *
     * @param array{expression: string, order: string} $config
     * @return array{sql: string, params: list<int>}
     */
    private static function buildSingleGameBranch(string $label, array $config, string $dateFilter, ?int $threshold): array
    {
        $safeLabel = str_replace("'", "''", $label);
        $safeOrder = $config['order'] === 'ASC' ? 'ASC' : 'DESC';
        $expression = $config['expression'];

        $cut = '';
        $params = [];
        if ($threshold !== null) {
            $cut = $safeOrder === 'ASC'
                ? "\n                    AND (" . $expression . ' <= ? OR ' . $expression . ' IS NULL)'
                : "\n                    AND " . $expression . ' >= ?';
            $params[] = $threshold;
        }
        $source = self::singleGameSource($threshold === null ? null : self::statIndex($expression, $dateFilter));

        // $expression and $dateFilter come from RecordStatDefinitions constants, never user input.
        // The chain starts with a variable so the SQL-concatenation rule sees a fragment, not a statement.
        $select = "(SELECT
                    '";
        $sql = $select . $safeLabel . "' AS stat_type,
                    t.teamid AS teamid,
                    t.team_name,
                    bs.game_date AS `date`,
                    COALESCE(sch.box_id, 0) AS box_id,
                    COALESCE(bs.game_of_that_day, 0) AS game_of_that_day,
                    CASE WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,
                    opp.team_name AS opp_team_name,
                    " . $expression . " AS value
                " . $source . "
                LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date
                    AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                    WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid
                    ELSE bs.visitor_teamid END
                " . self::singleGameWhere($dateFilter) . $cut
                // @phpstan-ignore ibl.orderByMissingTiebreaker ($safeOrder is a validated ASC/DESC constant; the full ORDER BY ends with the bs.id PK)
                . " ORDER BY value " . $safeOrder . ", bs.game_date ASC, bs.id ASC
                LIMIT " . self::SINGLE_GAME_LIMIT . ")";

        return ['sql' => $sql, 'params' => $params];
    }

    private static function statIndex(string $expression, string $dateFilter): ?string
    {
        if (!in_array($dateFilter, RecordStatDefinitions::DATE_FILTERS, true)) {
            return null;
        }

        return self::STAT_INDEXES[$expression] ?? null;
    }

    private static function singleGameSource(?string $index): string
    {
        if ($index === null) {
            return "FROM `ibl_box_scores_teams` bs
                JOIN `ibl_team_info` t ON t.team_name = bs.name";
        }

        return "FROM `ibl_box_scores_teams` bs FORCE INDEX (`" . $index . "`)
                STRAIGHT_JOIN `ibl_team_info` t ON t.team_name = bs.name";
    }

    private static function singleGameWhere(string $dateFilter): string
    {
        // @phpstan-ignore ibl.sqlStringConcatenation ($dateFilter is a RecordStatDefinitions::DATE_FILTERS constant or a caller-built date range, never user input)
        return "WHERE " . $dateFilter . "
                    AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                    AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID;
    }

    /**
     * Fetch the longest winning or losing streak across all teams.
     *
     * @return list<StreakRecord>
     */
    public function getLongestStreak(string $type): array
    {
        return StreakCalculator::longestStreak(
            $this->getRegularSeasonGames(),
            $type,
            fn (int $teamId): string => $this->resolveTeamName($teamId)
        );
    }

    /**
     * Fetch teams with the best or worst season start (first N games).
     *
     * @return list<SeasonStartRecord>
     */
    public function getBestWorstSeasonStart(string $type): array
    {
        return StreakCalculator::bestWorstSeasonStart(
            $this->getRegularSeasonGames(),
            $type,
            fn (int $teamId): string => $this->resolveTeamName($teamId)
        );
    }

    /**
     * Fetch all regular season games from `ibl_box_scores_teams`, cached for reuse.
     *
     * Both getLongestStreak() and getBestWorstSeasonStart() need the same data.
     *
     * @return list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}>
     */
    private function getRegularSeasonGames(): array
    {
        if ($this->regularSeasonGamesCache !== null) {
            return $this->regularSeasonGamesCache;
        }

        $regularSeasonFilter = 'game_type = 1';

        /** @var list<array{game_date: string, visitor_teamid: int, home_teamid: int, visitorScore: int, homeScore: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                game_date,
                visitor_teamid,
                home_teamid,
                visitorScore,
                homeScore
            FROM vw_team_total_score
            WHERE " . $regularSeasonFilter . "
                AND visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            GROUP BY game_date, visitor_teamid, home_teamid
            ORDER BY game_date ASC"
        );

        $this->regularSeasonGamesCache = $rows;
        return $rows;
    }

    /**
     * Resolve team name from team ID, using pre-loaded cache.
     */
    private function resolveTeamName(int $teamId): string
    {
        if ($this->teamNameCache === []) {
            /** @var list<array{teamid: int, team_name: string}> $rows */
            $rows = $this->fetchAll("SELECT teamid, team_name FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND ?", 'i', League::MAX_REAL_TEAMID);
            foreach ($rows as $row) {
                $this->teamNameCache[$row['teamid']] = $row['team_name'];
            }
        }

        return $this->teamNameCache[$teamId] ?? '';
    }
}
