<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Refresh the materialized ibl_team_season_records table from
 * ibl_box_scores_teams. Stores per-team-per-season win/loss totals for
 * regular-season (game_type=1) and HEAT (game_type=3) games. See ADR-0015.
 *
 * A (game_date, visitor_teamid, home_teamid) triple is one game: the league
 * has no doubleheaders. A repeat of the triple with a different
 * game_of_that_day is a phantom, and only the lowest-ordinal copy counts.
 * Same dedupe as FranchiseHistoryRepository::FIVE_SEASON_WINDOW_SQL.
 *
 * Runs DELETE + two INSERTs inside a transaction so the table is never
 * empty on error. Uses DELETE (not TRUNCATE) because TRUNCATE is DDL and
 * causes an implicit commit in MariaDB, breaking rollback safety.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RefreshTeamSeasonRecordsStep implements PipelineStepInterface
{
    use MysqliExecTrait;

    public function __construct(
        private readonly \mysqli $db,
    ) {
    }

    public function getLabel(): string
    {
        return 'team season records refreshed';
    }

    public function execute(): StepResult
    {
        $this->db->begin_transaction();

        try {
            $counts = $this->rebuildWithinCurrentTransaction();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d regular + %d HEAT rows', $counts['regular'], $counts['heat']),
        );
    }

    /**
     * DELETE + both INSERTs with no transaction control of its own.
     *
     * execute() wraps this in begin_transaction()/commit(). DatabaseIntegration
     * tests call it directly so the rebuild runs inside the per-test outer
     * transaction and is rolled back in tearDown; calling execute() there would
     * commit that outer transaction.
     *
     * @return array{regular: int, heat: int} affected-row counts of the two INSERTs
     * @throws \RuntimeException when a statement fails to prepare or execute
     */
    public function rebuildWithinCurrentTransaction(): array
    {
        $this->dbExec('DELETE FROM `ibl_team_season_records`');

        return [
            'regular' => $this->dbExec(self::INSERT_REGULAR_SEASON_SQL),
            'heat' => $this->dbExec(self::INSERT_HEAT_SQL),
        ];
    }

    /**
     * Regular-season records (game_type=1).
     * Year boundary: MONTH(game_date) >= 10 ? YEAR+1 : YEAR.
     *
     * Public for external readers; DatabaseIntegration tests should call rebuildWithinCurrentTransaction() instead of executing this string.
     */
    public const string INSERT_REGULAR_SEASON_SQL = <<<'SQL'
INSERT INTO `ibl_team_season_records`
    (team_id, `year`, game_type, currentname, namethatyear, wins, losses)
WITH canonical_games AS (
    SELECT game_date, visitor_teamid, home_teamid,
           MIN(game_of_that_day) AS game_of_that_day
    FROM `ibl_box_scores_teams`
    WHERE game_type = 1
    GROUP BY game_date, visitor_teamid, home_teamid
),
unique_games AS (
    SELECT
        b.game_date, b.visitor_teamid, b.home_teamid,
        MIN(b.id) AS visitor_row_id,
        MAX(b.id) AS home_row_id
    FROM `ibl_box_scores_teams` b
    JOIN canonical_games c
      ON c.game_date = b.game_date
     AND c.visitor_teamid = b.visitor_teamid
     AND c.home_teamid = b.home_teamid
     AND c.game_of_that_day <=> b.game_of_that_day
    WHERE b.game_type = 1
    GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
),
scored_games AS (
    -- Quarter columns are identical on both rows of a game, so they read from the
    -- visitor row. A NULL quarter (the restored 2004 Suns@Heat game) falls back to
    -- each side's own calc_points: v.calc_points for the visitor, h.calc_points for
    -- the home team. Same shape in FranchiseHistoryRepository and the ibl_team_win_loss view.
    SELECT
        u.game_date, u.visitor_teamid, u.home_teamid,
        COALESCE(v.visitor_q1_points + v.visitor_q2_points + v.visitor_q3_points + v.visitor_q4_points
                 + COALESCE(v.visitor_ot_points, 0), v.calc_points) AS visitor_total,
        COALESCE(v.home_q1_points + v.home_q2_points + v.home_q3_points + v.home_q4_points
                 + COALESCE(v.home_ot_points, 0), h.calc_points) AS home_total
    FROM unique_games u
    JOIN `ibl_box_scores_teams` v ON v.id = u.visitor_row_id
    JOIN `ibl_box_scores_teams` h ON h.id = u.home_row_id
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM scored_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM scored_games
)
SELECT
    tg.team_id,
    CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1
         ELSE YEAR(tg.game_date) END AS `year`,
    1 AS game_type,
    ti.team_name AS currentname,
    COALESCE(fs.team_name, ti.team_name) AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED) AS losses
FROM team_games tg
JOIN `ibl_team_info` ti ON ti.teamid = tg.team_id
LEFT JOIN `ibl_franchise_seasons` fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (
        CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1
             ELSE YEAR(tg.game_date) END
    )
GROUP BY
    tg.team_id,
    CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1 ELSE YEAR(tg.game_date) END,
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name)
SQL;

    /**
     * HEAT records (game_type=3). Year is YEAR(game_date), franchise lookup
     * uses year+1 to match the season-ending convention. Filters out the
     * 9000+ sentinel year used for unscheduled HEAT games.
     */
    private const string INSERT_HEAT_SQL = <<<'SQL'
INSERT INTO `ibl_team_season_records`
    (team_id, `year`, game_type, currentname, namethatyear, wins, losses)
WITH canonical_games AS (
    SELECT game_date, visitor_teamid, home_teamid,
           MIN(game_of_that_day) AS game_of_that_day
    FROM `ibl_box_scores_teams`
    WHERE game_type = 3
      AND YEAR(game_date) < 9000
    GROUP BY game_date, visitor_teamid, home_teamid
),
unique_games AS (
    SELECT
        b.game_date, b.visitor_teamid, b.home_teamid,
        MIN(b.id) AS visitor_row_id,
        MAX(b.id) AS home_row_id
    FROM `ibl_box_scores_teams` b
    JOIN canonical_games c
      ON c.game_date = b.game_date
     AND c.visitor_teamid = b.visitor_teamid
     AND c.home_teamid = b.home_teamid
     AND c.game_of_that_day <=> b.game_of_that_day
    WHERE b.game_type = 3
      AND YEAR(b.game_date) < 9000
    GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
),
scored_games AS (
    -- Quarter columns are identical on both rows of a game, so they read from the
    -- visitor row. A NULL quarter (the restored 2004 Suns@Heat game) falls back to
    -- each side's own calc_points: v.calc_points for the visitor, h.calc_points for
    -- the home team. Same shape in FranchiseHistoryRepository and the ibl_team_win_loss view.
    SELECT
        u.game_date, u.visitor_teamid, u.home_teamid,
        COALESCE(v.visitor_q1_points + v.visitor_q2_points + v.visitor_q3_points + v.visitor_q4_points
                 + COALESCE(v.visitor_ot_points, 0), v.calc_points) AS visitor_total,
        COALESCE(v.home_q1_points + v.home_q2_points + v.home_q3_points + v.home_q4_points
                 + COALESCE(v.home_ot_points, 0), h.calc_points) AS home_total
    FROM unique_games u
    JOIN `ibl_box_scores_teams` v ON v.id = u.visitor_row_id
    JOIN `ibl_box_scores_teams` h ON h.id = u.home_row_id
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM scored_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM scored_games
)
SELECT
    tg.team_id,
    YEAR(tg.game_date) AS `year`,
    3 AS game_type,
    ti.team_name AS currentname,
    COALESCE(fs.team_name, ti.team_name) AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED) AS losses
FROM team_games tg
JOIN `ibl_team_info` ti ON ti.teamid = tg.team_id
LEFT JOIN `ibl_franchise_seasons` fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (YEAR(tg.game_date) + 1)
GROUP BY
    tg.team_id,
    YEAR(tg.game_date),
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name)
SQL;
}
