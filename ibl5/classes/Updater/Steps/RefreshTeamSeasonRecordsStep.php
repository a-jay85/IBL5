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
 * Runs DELETE + two INSERTs inside a transaction so the table is never
 * empty on error. Uses DELETE (not TRUNCATE) because TRUNCATE is DDL and
 * causes an implicit commit in MariaDB, breaking rollback safety.
 *
 * IBL-only — Olympics league does not use this step.
 */
final class RefreshTeamSeasonRecordsStep implements PipelineStepInterface
{
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
            if ($this->db->query('DELETE FROM ibl_team_season_records') === false) {
                throw new \RuntimeException('DELETE failed: ' . $this->db->error);
            }
            if ($this->db->query(self::INSERT_REGULAR_SEASON_SQL) === false) {
                throw new \RuntimeException('Regular-season INSERT failed: ' . $this->db->error);
            }
            $regularRows = (int) $this->db->affected_rows;
            if ($this->db->query(self::INSERT_HEAT_SQL) === false) {
                throw new \RuntimeException('HEAT INSERT failed: ' . $this->db->error);
            }
            $heatRows = (int) $this->db->affected_rows;
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return StepResult::failure($this->getLabel(), $e->getMessage());
        }

        return StepResult::success(
            $this->getLabel(),
            sprintf('%d regular + %d HEAT rows', $regularRows, $heatRows),
        );
    }

    /**
     * Regular-season records (game_type=1).
     * Year boundary: MONTH(game_date) >= 10 ? YEAR+1 : YEAR.
     */
    private const string INSERT_REGULAR_SEASON_SQL = <<<'SQL'
INSERT INTO ibl_team_season_records
    (team_id, `year`, game_type, currentname, namethatyear, wins, losses)
WITH unique_games AS (
    SELECT
        game_date, visitor_teamid, home_teamid, game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS visitor_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 1
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
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
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
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
INSERT INTO ibl_team_season_records
    (team_id, `year`, game_type, currentname, namethatyear, wins, losses)
WITH unique_games AS (
    SELECT
        game_date, visitor_teamid, home_teamid, game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS visitor_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 3
      AND YEAR(game_date) < 9000
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
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
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (YEAR(tg.game_date) + 1)
GROUP BY
    tg.team_id,
    YEAR(tg.game_date),
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name)
SQL;
}
