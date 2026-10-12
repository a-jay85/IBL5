<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;
use League\League;

/**
 * FranchiseHistoryRepository - Data access layer for franchise history
 *
 * Retrieves raw franchise history rows from the database. All cross-source
 * merging and derived-field computation lives in {@see FranchiseHistoryService};
 * this class returns only the raw rows the queries produce.
 *
 * @phpstan-import-type SummaryRow from \FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface
 * @phpstan-import-type WindowRow from \FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface
 * @phpstan-import-type PlayoffTotalRow from \FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface
 * @phpstan-import-type HeatTotalRow from \FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface
 *
 * @see FranchiseHistoryRepositoryInterface For the interface contract
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class FranchiseHistoryRepository extends \Database\BaseMysqliRepository implements FranchiseHistoryRepositoryInterface
{
    /**
     * Rolling five-season window. Inlines the CTE chain of the team win/loss view
     * with a sargable game_date range (see getFiveSeasonWindowRows docblock for the
     * equivalence argument). Public so the DB-integration perf report can EXPLAIN it.
     */
    public const FIVE_SEASON_WINDOW_SQL = <<<'SQL'
        WITH canonical_games AS (
            SELECT game_date, visitor_teamid, home_teamid, MIN(game_of_that_day) AS game_of_that_day
            FROM `ibl_box_scores_teams`
            WHERE game_type = 1 AND game_date >= ? AND game_date < ?
            GROUP BY game_date, visitor_teamid, home_teamid
        ), unique_games AS (
            SELECT b.game_date, b.visitor_teamid, b.home_teamid,
                   b.visitor_q1_points + b.visitor_q2_points + b.visitor_q3_points + b.visitor_q4_points + COALESCE(b.visitor_ot_points, 0) AS visitor_total,
                   b.home_q1_points + b.home_q2_points + b.home_q3_points + b.home_q4_points + COALESCE(b.home_ot_points, 0) AS home_total
            FROM `ibl_box_scores_teams` b
            JOIN canonical_games c
              ON c.game_date = b.game_date AND c.visitor_teamid = b.visitor_teamid
             AND c.home_teamid = b.home_teamid AND c.game_of_that_day <=> b.game_of_that_day
            WHERE b.game_type = 1 AND b.game_date >= ? AND b.game_date < ?
            GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
        ), team_games AS (
            SELECT visitor_teamid AS teamid, game_date,
                   IF(visitor_total > home_total, 1, 0) AS win, IF(visitor_total < home_total, 1, 0) AS loss
            FROM unique_games
            UNION ALL
            SELECT home_teamid AS teamid, game_date,
                   IF(home_total > visitor_total, 1, 0) AS win, IF(home_total < visitor_total, 1, 0) AS loss
            FROM unique_games
        ), season_rows AS (
            SELECT CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1 ELSE YEAR(tg.game_date) END AS year,
                   ti.team_name AS currentname,
                   COALESCE(fs.team_name, ti.team_name) AS namethatyear,
                   CAST(SUM(tg.win) AS UNSIGNED) AS wins,
                   CAST(SUM(tg.loss) AS UNSIGNED) AS losses
            FROM team_games tg
            JOIN `ibl_team_info` ti ON ti.teamid = tg.teamid
            LEFT JOIN `ibl_franchise_seasons` fs
              ON fs.franchise_id = tg.teamid
             AND fs.season_ending_year = CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1 ELSE YEAR(tg.game_date) END
            GROUP BY tg.teamid,
                     CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1 ELSE YEAR(tg.game_date) END,
                     ti.team_name, COALESCE(fs.team_name, ti.team_name)
        )
        SELECT currentname,
               CAST(SUM(wins) AS UNSIGNED) AS five_season_wins,
               CAST(SUM(losses) AS UNSIGNED) AS five_season_losses
        FROM season_rows
        WHERE year BETWEEN ? AND ?
        GROUP BY currentname
        SQL;

    /**
     * @see FranchiseHistoryRepositoryInterface::getFranchiseSummaryRows()
     *
     * @return list<SummaryRow>
     */
    public function getFranchiseSummaryRows(int $currentEndingYear): array
    {
        // All-time totals from vw_franchise_summary (no redundant ibl_team_win_loss JOIN)
        /** @var list<SummaryRow> $summaryRows */
        $summaryRows = $this->fetchAll(
            "SELECT ti.teamid, ti.team_name, ti.color1, ti.color2,
                    fs.totwins, fs.totloss, fs.winpct, fs.playoffs,
                    fs.div_titles, fs.conf_titles, fs.ibl_titles, fs.heat_titles
             FROM `ibl_team_info` ti
             JOIN vw_franchise_summary fs ON fs.teamid = ti.teamid
             WHERE ti.teamid <> ?
             ORDER BY ti.teamid ASC",
            "i",
            League::FREE_AGENTS_TEAMID
        );

        return $summaryRows;
    }

    /**
     * The team win/loss view derives a season-ending `year` of
     * MONTH(game_date) >= 10 ? YEAR + 1 : YEAR, and game_type = 1 already excludes
     * months 6, 9 and 10. So for ending years A..B, a game_type = 1 row has `year`
     * in [A, B] exactly when game_date >= '(A-1)-10-01' AND game_date < 'B-10-01'.
     * game_date is a GROUP BY key in both canonical_games and unique_games, so the
     * range keeps or drops whole groups and MIN(game_of_that_day) is unchanged. The
     * outer `year BETWEEN` filter stays, so the range only removes rows it would
     * also remove, while letting the box-score scans use a game_date range.
     *
     * @see FranchiseHistoryRepositoryInterface::getFiveSeasonWindowRows()
     *
     * @return list<WindowRow>
     */
    public function getFiveSeasonWindowRows(int $currentEndingYear): array
    {
        $fiveSeasonsAgoEndingYear = $currentEndingYear - 4;

        // Sargable window: game_type = 1 rows whose season-ending year is in
        // [$fiveSeasonsAgoEndingYear, $currentEndingYear] are exactly the rows with
        // game_date in [(start - 1)-10-01, end-10-01).
        $rangeStart = sprintf('%04d-10-01', $fiveSeasonsAgoEndingYear - 1);
        $rangeEnd = sprintf('%04d-10-01', $currentEndingYear);

        /** @var list<WindowRow> $windowRows */
        $windowRows = $this->fetchAll(
            self::FIVE_SEASON_WINDOW_SQL,
            "ssssii",
            $rangeStart,
            $rangeEnd,
            $rangeStart,
            $rangeEnd,
            $fiveSeasonsAgoEndingYear,
            $currentEndingYear
        );

        return $windowRows;
    }

    /**
     * Get aggregated playoff game wins and losses for all teams in bulk (raw totals).
     *
     * Uses a single SELECT from vw_playoff_series_results with conditional aggregation
     * to compute both winner and loser game tallies without UNION ALL (avoids double materialization).
     *
     * @see FranchiseHistoryRepositoryInterface::getRawPlayoffTotals()
     *
     * @return list<PlayoffTotalRow>
     */
    public function getRawPlayoffTotals(): array
    {
        /** @var list<PlayoffTotalRow> $rows */
        $rows = $this->fetchAll(
            "SELECT
                team_name,
                CAST(SUM(wins) AS UNSIGNED) AS total_wins,
                CAST(SUM(losses) AS UNSIGNED) AS total_losses
            FROM (
                SELECT winner AS team_name, winner_games AS wins, loser_games AS losses
                FROM vw_playoff_series_results
                UNION ALL
                SELECT loser AS team_name, loser_games AS wins, winner_games AS losses
                FROM vw_playoff_series_results
            ) AS combined
            GROUP BY team_name"
        );

        return $rows;
    }

    /**
     * Get aggregated HEAT wins and losses for all teams in bulk (raw totals).
     *
     * @see FranchiseHistoryRepositoryInterface::getRawHeatTotals()
     *
     * @return list<HeatTotalRow>
     */
    public function getRawHeatTotals(): array
    {
        /** @var list<HeatTotalRow> $rows */
        $rows = $this->fetchAll(
            "SELECT currentname, SUM(wins) AS total_wins, SUM(losses) AS total_losses
            FROM `ibl_heat_win_loss`
            GROUP BY currentname"
        );

        return $rows;
    }
}
