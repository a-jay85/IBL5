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
final class TeamRecordRepository extends \BaseMysqliRepository
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
                AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY value " . $safeOrder . ", bs.game_date ASC
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
        $query = "WITH _game_of_day AS " . $this->gameOfThatDaySubquery() . "
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
                twl.currentname AS team_name,
                twl.year,
                twl.wins,
                twl.losses
            FROM `ibl_team_win_loss` twl
            JOIN `ibl_team_info` ti ON ti.team_name = twl.currentname
            WHERE ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND (twl.wins + twl.losses) > 0
            ORDER BY (twl.wins / (twl.wins + twl.losses)) " . $safeOrder . ",
                twl.wins " . $safeOrder . "
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

        $unions = [];
        foreach ($statExpressions as $label => $config) {
            $safeLabel = str_replace("'", "''", $label);
            $safeOrder = $config['order'] === 'ASC' ? 'ASC' : 'DESC';
            $unions[] = "(SELECT
                    '" . $safeLabel . "' AS stat_type,
                    t.teamid AS teamid,
                    t.team_name,
                    bs.game_date AS `date`,
                    COALESCE(sch.box_id, 0) AS box_id,
                    COALESCE(bs.game_of_that_day, 0) AS game_of_that_day,
                    CASE WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,
                    opp.team_name AS opp_team_name,
                    " . $config['expression'] . " AS value
                FROM `ibl_box_scores_teams` bs
                JOIN `ibl_team_info` t ON t.team_name = bs.name
                LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date
                    AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                    WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid
                    ELSE bs.visitor_teamid END
                WHERE " . $dateFilter . "
                    AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                    AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                ORDER BY value " . $safeOrder . ", bs.game_date ASC
                LIMIT 5)";
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<TeamSingleGameRecord>> $results */
        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
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
                bs.game_date,
                bs.visitor_teamid,
                bs.home_teamid,
                bs.visitor_q1_points + bs.visitor_q2_points + bs.visitor_q3_points + bs.visitor_q4_points + COALESCE(bs.visitor_ot_points, 0) AS visitorScore,
                bs.home_q1_points + bs.home_q2_points + bs.home_q3_points + bs.home_q4_points + COALESCE(bs.home_ot_points, 0) AS homeScore
            FROM `ibl_box_scores_teams` bs
            WHERE " . $regularSeasonFilter . "
                AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY bs.game_date ASC, bs.id ASC"
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
