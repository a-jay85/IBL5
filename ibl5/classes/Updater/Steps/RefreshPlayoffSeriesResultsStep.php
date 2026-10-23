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
    use MysqliExecTrait;

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
            $rowCount = $this->rebuildWithinCurrentTransaction();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success($this->getLabel(), $rowCount . ' rows');
    }

    /**
     * DELETE + INSERT with no transaction control of its own.
     *
     * execute() wraps this in begin_transaction()/commit(). DatabaseIntegration
     * tests call it directly so the rebuild runs inside the per-test outer
     * transaction and is rolled back in tearDown; calling execute() there would
     * commit that outer transaction.
     *
     * @return int affected-row count of the INSERT
     * @throws \RuntimeException when a statement fails to prepare or execute
     */
    public function rebuildWithinCurrentTransaction(): int
    {
        $this->dbExec('DELETE FROM `ibl_playoff_series_results`');

        return $this->dbExec(
            'INSERT INTO `ibl_playoff_series_results` '
            . '(`year`, `round`, `winner_tid`, `loser_tid`, `winner`, `loser`, '
            . '`winner_games`, `loser_games`, `total_games`) '
            . self::SELECT_SQL,
        );
    }

    /**
     * The canonical SELECT that derives playoff series rows from
     * ibl_box_scores_teams, counting each date/visitor/home game once
     * (lowest game_of_that_day wins). Migration 123 embeds an older form
     * that grouped by the 4-tuple including game_of_that_day; that applied
     * migration is left as is, and every pipeline run rebuilds the table
     * from this constant.
     */
    private const string SELECT_SQL = <<<'SQL'
WITH canonical_games AS (
    SELECT game_date, visitor_teamid, home_teamid,
           MIN(game_of_that_day) AS game_of_that_day
    FROM `ibl_box_scores_teams`
    WHERE game_type = 2
    GROUP BY game_date, visitor_teamid, home_teamid
),
playoff_games AS (
    SELECT
        b.game_date,
        YEAR(b.game_date) AS `year`,
        b.visitor_teamid,
        b.home_teamid,
        (b.visitor_q1_points + b.visitor_q2_points + b.visitor_q3_points + b.visitor_q4_points
         + COALESCE(b.visitor_ot_points, 0)) AS v_total,
        (b.home_q1_points + b.home_q2_points + b.home_q3_points + b.home_q4_points
         + COALESCE(b.home_ot_points, 0)) AS h_total
    FROM `ibl_box_scores_teams` b
    JOIN canonical_games c
      ON c.game_date = b.game_date
     AND c.visitor_teamid = b.visitor_teamid
     AND c.home_teamid = b.home_teamid
     AND c.game_of_that_day <=> b.game_of_that_day
    WHERE b.game_type = 2
    -- LOAD-BEARING: ibl_box_scores_teams stores one row per TEAM side, two rows
    -- per game (uq_game_team spans date, visitor, home, game_of_that_day and name).
    -- canonical_games keeps the lowest game_of_that_day per date/visitor/home
    -- triple. The league plays no doubleheaders, so a repeated triple with a
    -- different ordinal is a phantom import and is dropped here. The <=> join
    -- keeps a game whose ordinal is NULL. This GROUP BY then collapses the two
    -- team-side rows; without it every series counts twice and a 4-1 becomes
    -- 8-2. Same shape as RefreshTeamSeasonRecordsStep. Do not remove.
    GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
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
JOIN `ibl_team_info` w ON w.teamid = tw.winner_tid
JOIN `ibl_team_info` l ON l.teamid = CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END
SQL;
}
