<?php

declare(strict_types=1);

namespace FranchiseHistory\Contracts;

/**
 * FranchiseHistoryRepositoryInterface - Contract for franchise history data access
 *
 * Defines methods for retrieving franchise history data from the database.
 *
 * @phpstan-type FranchiseRow array{teamid: int|string, team_name: string, color1: string, color2: string, totwins: int|string, totloss: int|string, winpct: string, five_season_wins: int|string, five_season_losses: int|string, five_season_winpct: string|null, totalgames: int|string, playoffs: int|string, playoff_total_wins: int, playoff_total_losses: int, playoff_winpct: string, heat_total_wins: int, heat_total_losses: int, heat_winpct: string, heat_titles: int, div_titles: int, conf_titles: int, ibl_titles: int, ...}
 *
 * @see \FranchiseHistory\FranchiseHistoryRepository For the concrete implementation
 */
interface FranchiseHistoryRepositoryInterface
{
    /**
     * Get all franchise history data with win/loss records
     *
     * @param int $currentEndingYear Current season ending year
     * @return array<int, FranchiseRow> Array of franchise history data
     */
    public function getAllFranchiseHistory(int $currentEndingYear): array;
}
