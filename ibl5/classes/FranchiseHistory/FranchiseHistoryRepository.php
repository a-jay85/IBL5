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
 * @see \BaseMysqliRepository For base class documentation
 */
class FranchiseHistoryRepository extends \BaseMysqliRepository implements FranchiseHistoryRepositoryInterface
{
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
     * @see FranchiseHistoryRepositoryInterface::getFiveSeasonWindowRows()
     *
     * @return list<WindowRow>
     */
    public function getFiveSeasonWindowRows(int $currentEndingYear): array
    {
        $fiveSeasonsAgoEndingYear = $currentEndingYear - 4;

        // Rolling 5-season window from `ibl_team_win_loss` directly (avoids double materialization)
        /** @var list<WindowRow> $windowRows */
        $windowRows = $this->fetchAll(
            "SELECT currentname,
                    CAST(SUM(wins) AS UNSIGNED) AS five_season_wins,
                    CAST(SUM(losses) AS UNSIGNED) AS five_season_losses
             FROM `ibl_team_win_loss`
             WHERE year BETWEEN ? AND ?
             GROUP BY currentname",
            "ii",
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
