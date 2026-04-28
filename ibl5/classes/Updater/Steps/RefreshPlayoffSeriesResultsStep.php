<?php

declare(strict_types=1);

namespace Updater\Steps;

use Updater\Contracts\PipelineStepInterface;
use Updater\StepResult;

/**
 * Refresh the materialized ibl_playoff_series_results table from
 * ibl_box_scores_teams (game_type=2). The vw_playoff_series_results view
 * is a thin pass-through over this table — see ADR-0015.
 *
 * Runs DELETE + INSERT inside a transaction so the table is never empty on
 * error. Uses DELETE (not TRUNCATE) because TRUNCATE is DDL and causes an
 * implicit commit in MariaDB, breaking rollback safety.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RefreshPlayoffSeriesResultsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly \mysqli $db,
    ) {
    }

    public function getLabel(): string
    {
        return 'playoff series results refreshed';
    }

    public function execute(): StepResult
    {
        $this->db->begin_transaction();

        try {
            if ($this->db->query('DELETE FROM ibl_playoff_series_results') === false) {
                throw new \RuntimeException('DELETE failed: ' . $this->db->error);
            }
            if ($this->db->query(
                'INSERT INTO ibl_playoff_series_results '
                . '(`year`, `round`, `winner_tid`, `loser_tid`, `winner`, `loser`, '
                . '`winner_games`, `loser_games`, `total_games`) '
                . self::SELECT_SQL,
            ) === false) {
                throw new \RuntimeException('INSERT failed: ' . $this->db->error);
            }
            $rowCount = $this->db->affected_rows;
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success($this->getLabel(), $rowCount . ' rows');
    }

    /**
     * The canonical SELECT that derives playoff series rows from the
     * deduplicated game-by-game ibl_box_scores_teams. Identical to the CTE
     * embedded in migration 123.
     */
    private const string SELECT_SQL = <<<'SQL'
WITH playoff_games AS (
    SELECT
        game_date,
        YEAR(game_date) AS `year`,
        visitor_teamid,
        home_teamid,
        game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS v_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS h_total
    FROM ibl_box_scores_teams
    WHERE game_type = 2
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
game_results AS (
    SELECT *,
        CASE WHEN v_total > h_total THEN visitor_teamid ELSE home_teamid END AS winner_tid,
        CASE WHEN v_total > h_total THEN home_teamid ELSE visitor_teamid END AS loser_tid
    FROM playoff_games
),
team_wins AS (
    SELECT
        `year`,
        LEAST(visitor_teamid, home_teamid) AS team_a,
        GREATEST(visitor_teamid, home_teamid) AS team_b,
        winner_tid,
        COUNT(*) AS wins,
        ROW_NUMBER() OVER (
            PARTITION BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid)
            ORDER BY COUNT(*) DESC
        ) AS rn
    FROM game_results
    GROUP BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid), winner_tid
),
series_meta AS (
    SELECT
        `year`,
        LEAST(visitor_teamid, home_teamid) AS team_a,
        GREATEST(visitor_teamid, home_teamid) AS team_b,
        COUNT(*) AS total_games,
        MIN(game_date) AS series_start,
        DENSE_RANK() OVER (PARTITION BY `year` ORDER BY MIN(game_date)) AS `round`
    FROM game_results
    GROUP BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid)
)
SELECT
    sm.`year`,
    sm.`round`,
    tw.winner_tid,
    CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END AS loser_tid,
    w.team_name AS winner,
    l.team_name AS loser,
    tw.wins AS winner_games,
    sm.total_games - tw.wins AS loser_games,
    sm.total_games
FROM series_meta sm
JOIN team_wins tw
    ON tw.`year` = sm.`year` AND tw.team_a = sm.team_a AND tw.team_b = sm.team_b AND tw.rn = 1
JOIN ibl_team_info w ON w.teamid = tw.winner_tid
JOIN ibl_team_info l ON l.teamid = CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END
SQL;
}
