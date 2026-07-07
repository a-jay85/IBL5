<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;
use FranchiseHistory\Contracts\FranchiseHistoryServiceInterface;

/**
 * FranchiseHistoryService - Business logic for franchise history
 *
 * Owns the assembly that the Repository sheds: merges the all-time summary,
 * the rolling 5-season window, playoff totals, and HEAT totals into one display
 * row per team, deriving win percentages and applying absent-source defaults.
 *
 * @phpstan-import-type FranchiseRow from FranchiseHistoryServiceInterface
 *
 * @see FranchiseHistoryServiceInterface For the interface contract
 */
class FranchiseHistoryService implements FranchiseHistoryServiceInterface
{
    public function __construct(private FranchiseHistoryRepositoryInterface $repository)
    {
    }

    /**
     * @see FranchiseHistoryServiceInterface::getAllFranchiseHistory()
     *
     * @return array<int, FranchiseRow>
     */
    public function getAllFranchiseHistory(int $currentEndingYear): array
    {
        $summaryRows = $this->repository->getFranchiseSummaryRows($currentEndingYear);
        $windowRows = $this->repository->getFiveSeasonWindowRows($currentEndingYear);

        /** @var array<string, array{five_season_wins: int, five_season_losses: int}> $windowByTeam */
        $windowByTeam = [];
        foreach ($windowRows as $row) {
            $windowByTeam[$row['currentname']] = [
                'five_season_wins' => $row['five_season_wins'],
                'five_season_losses' => $row['five_season_losses'],
            ];
        }

        $allPlayoffTotals = $this->buildPlayoffTotals();
        $allHeatTotals = $this->buildHeatTotals();

        /** @var array<int, FranchiseRow> $teams */
        $teams = [];
        foreach ($summaryRows as $summary) {
            $teamName = $summary['team_name'];
            $window = $windowByTeam[$teamName] ?? ['five_season_wins' => 0, 'five_season_losses' => 0];
            $fiveWins = $window['five_season_wins'];
            $fiveLosses = $window['five_season_losses'];
            $totalGames = $fiveWins + $fiveLosses;
            $fiveWinpct = $totalGames > 0
                ? \BasketballStats\StatsFormatter::formatPercentage($fiveWins, $totalGames)
                : null;

            $playoffTotals = $allPlayoffTotals[$teamName] ?? ['wins' => 0, 'losses' => 0, 'winpct' => '.000'];
            $heatTotals = $allHeatTotals[$teamName] ?? ['wins' => 0, 'losses' => 0, 'winpct' => '.000'];

            $teams[] = [
                'teamid' => $summary['teamid'],
                'team_name' => $teamName,
                'color1' => $summary['color1'],
                'color2' => $summary['color2'],
                'totwins' => $summary['totwins'],
                'totloss' => $summary['totloss'],
                'winpct' => $summary['winpct'],
                'playoffs' => $summary['playoffs'],
                'five_season_wins' => $fiveWins,
                'five_season_losses' => $fiveLosses,
                'totalgames' => $totalGames,
                'five_season_winpct' => $fiveWinpct,
                'playoff_total_wins' => $playoffTotals['wins'],
                'playoff_total_losses' => $playoffTotals['losses'],
                'playoff_winpct' => $playoffTotals['winpct'],
                'heat_total_wins' => $heatTotals['wins'],
                'heat_total_losses' => $heatTotals['losses'],
                'heat_winpct' => $heatTotals['winpct'],
                'heat_titles' => $summary['heat_titles'],
                'div_titles' => $summary['div_titles'],
                'conf_titles' => $summary['conf_titles'],
                'ibl_titles' => $summary['ibl_titles'],
            ];
        }

        return $teams;
    }

    /**
     * Build a team-name → playoff totals map with derived winpct from raw repository rows.
     *
     * @return array<string, array{wins: int, losses: int, winpct: string}>
     */
    private function buildPlayoffTotals(): array
    {
        $result = [];
        foreach ($this->repository->getRawPlayoffTotals() as $row) {
            $wins = $row['total_wins'];
            $losses = $row['total_losses'];
            $totalGames = $wins + $losses;
            $winpct = $totalGames > 0
                ? \BasketballStats\StatsFormatter::formatPercentage($wins, $totalGames)
                : '.000';
            $result[$row['team_name']] = ['wins' => $wins, 'losses' => $losses, 'winpct' => $winpct];
        }

        return $result;
    }

    /**
     * Build a team-name → HEAT totals map with derived winpct from raw repository rows.
     *
     * @return array<string, array{wins: int, losses: int, winpct: string}>
     */
    private function buildHeatTotals(): array
    {
        $result = [];
        foreach ($this->repository->getRawHeatTotals() as $row) {
            $wins = $row['total_wins'] ?? 0;
            $losses = $row['total_losses'] ?? 0;
            $totalGames = $wins + $losses;
            $winpct = $totalGames > 0
                ? \BasketballStats\StatsFormatter::formatPercentage($wins, $totalGames)
                : '.000';
            $result[$row['currentname']] = ['wins' => $wins, 'losses' => $losses, 'winpct' => $winpct];
        }

        return $result;
    }
}
