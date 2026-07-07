<?php

declare(strict_types=1);

namespace FranchiseHistory\Contracts;

/**
 * FranchiseHistoryRepositoryInterface - Contract for franchise history data access
 *
 * Defines methods for retrieving raw franchise history rows from the database.
 * All cross-source merging and derived-field computation lives in
 * {@see \FranchiseHistory\FranchiseHistoryService}; this contract returns only
 * the raw rows the queries produce.
 *
 * @phpstan-type SummaryRow array{teamid: int, team_name: string, color1: string, color2: string, totwins: int, totloss: int, winpct: string, playoffs: int, div_titles: int, conf_titles: int, ibl_titles: int, heat_titles: int}
 * @phpstan-type WindowRow array{currentname: string, five_season_wins: int, five_season_losses: int}
 * @phpstan-type PlayoffTotalRow array{team_name: string, total_wins: int, total_losses: int}
 * @phpstan-type HeatTotalRow array{currentname: string, total_wins: int|null, total_losses: int|null}
 *
 * @see \FranchiseHistory\FranchiseHistoryRepository For the concrete implementation
 */
interface FranchiseHistoryRepositoryInterface
{
    /**
     * Get all-time franchise summary rows (one per real team), ordered by teamid ASC.
     *
     * @param int $currentEndingYear Current season ending year (unused by the query today,
     *                               kept for symmetry / future filtering)
     * @return list<SummaryRow>
     */
    public function getFranchiseSummaryRows(int $currentEndingYear): array;

    /**
     * Get rolling 5-season window win/loss sums per team name (raw, ungrouped into a map).
     *
     * @param int $currentEndingYear Current season ending year (window is the 5 seasons ending here)
     * @return list<WindowRow>
     */
    public function getFiveSeasonWindowRows(int $currentEndingYear): array;

    /**
     * Get aggregated playoff game wins/losses per team name (raw totals, no derived winpct).
     *
     * @return list<PlayoffTotalRow>
     */
    public function getRawPlayoffTotals(): array;

    /**
     * Get aggregated HEAT wins/losses per team name (raw totals, no derived winpct).
     *
     * @return list<HeatTotalRow>
     */
    public function getRawHeatTotals(): array;
}
