<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;

/**
 * FranchiseHistoryRepository - Data access layer for franchise history
 *
 * Retrieves franchise history and win/loss records from the database.
 *
 * @phpstan-import-type FranchiseRow from \FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface
 *
 * @see FranchiseHistoryRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class FranchiseHistoryRepository extends \BaseMysqliRepository implements FranchiseHistoryRepositoryInterface
{
    /**
     * @see FranchiseHistoryRepositoryInterface::getAllFranchiseHistory()
     *
     * @return array<int, FranchiseRow>
     */
    public function getAllFranchiseHistory(int $currentEndingYear): array
    {
        $fiveSeasonsAgoEndingYear = $currentEndingYear - 4;

        $query = "SELECT
            ibl_team_history.*,
            SUM(ibl_team_win_loss.wins) as five_season_wins,
            SUM(ibl_team_win_loss.losses) as five_season_losses,
            (SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses)) as totalgames,
            ROUND((SUM(ibl_team_win_loss.wins) / (SUM(ibl_team_win_loss.wins) + SUM(ibl_team_win_loss.losses))), 3) as five_season_winpct
            FROM ibl_team_history
            INNER JOIN ibl_team_win_loss ON ibl_team_win_loss.currentname = ibl_team_history.team_name
            WHERE teamid != ?
            AND year BETWEEN ? AND ?
            GROUP BY currentname
            ORDER BY teamid ASC";

        /** @var array<int, array{team_name: string, teamid: int|string, color1: string, color2: string, totwins: int|string, totloss: int|string, winpct: string, five_season_wins: int|string, five_season_losses: int|string, five_season_winpct: string|null, totalgames: int|string, playoffs: int|string}> $teams */
        $teams = $this->fetchAll(
            $query,
            "iii",
            \League::FREE_AGENTS_TEAMID,
            $fiveSeasonsAgoEndingYear,
            $currentEndingYear
        );

        // Bulk-fetch playoff totals, HEAT totals, and title counts for all teams
        $allPlayoffTotals = $this->getAllPlayoffTotals();
        $allHeatTotals = $this->getAllHEATTotals();
        $allTitleCounts = $this->getAllTitleCounts();

        foreach ($teams as &$team) {
            $teamName = $team['team_name'];
            $playoffTotals = $allPlayoffTotals[$teamName] ?? ['wins' => 0, 'losses' => 0, 'winpct' => '.000'];
            $team['playoff_total_wins'] = $playoffTotals['wins'];
            $team['playoff_total_losses'] = $playoffTotals['losses'];
            $team['playoff_winpct'] = $playoffTotals['winpct'];
            $heatTotals = $allHeatTotals[$teamName] ?? ['wins' => 0, 'losses' => 0, 'winpct' => '.000'];
            $team['heat_total_wins'] = $heatTotals['wins'];
            $team['heat_total_losses'] = $heatTotals['losses'];
            $team['heat_winpct'] = $heatTotals['winpct'];
            $titleCounts = $allTitleCounts[$teamName] ?? ['heat_titles' => 0, 'div_titles' => 0, 'conf_titles' => 0, 'ibl_titles' => 0];
            $team['heat_titles'] = $titleCounts['heat_titles'];
            $team['div_titles'] = $titleCounts['div_titles'];
            $team['conf_titles'] = $titleCounts['conf_titles'];
            $team['ibl_titles'] = $titleCounts['ibl_titles'];
        }

        /** @var array<int, FranchiseRow> $teams */
        return $teams;
    }

    /**
     * Get aggregated playoff game wins and losses for all teams in bulk
     *
     * Derives game-level records from series results in vw_playoff_series_results:
     * - When team is the winner: +winner_games wins, +loser_games losses
     * - When team is the loser: +loser_games wins, +winner_games losses
     *
     * @return array<string, array{wins: int, losses: int, winpct: string}> Map of team name → playoff totals
     */
    private function getAllPlayoffTotals(): array
    {
        $rows = $this->fetchAll(
            "SELECT
                team_name,
                SUM(CASE WHEN team_name = winner THEN winner_games ELSE loser_games END) AS total_wins,
                SUM(CASE WHEN team_name = winner THEN loser_games ELSE winner_games END) AS total_losses
            FROM (
                SELECT winner AS team_name, winner, winner_games, loser_games FROM vw_playoff_series_results
                UNION ALL
                SELECT loser AS team_name, winner, winner_games, loser_games FROM vw_playoff_series_results
            ) AS combined
            GROUP BY team_name"
        );

        /** @var array<string, array{wins: int, losses: int, winpct: string}> $result */
        $result = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, total_wins: int|null, total_losses: int|null} $row */
            $wins = $row['total_wins'] ?? 0;
            $losses = $row['total_losses'] ?? 0;
            $totalGames = $wins + $losses;
            $winpct = $totalGames > 0
                ? number_format($wins / $totalGames, 3)
                : '.000';
            $result[$row['team_name']] = ['wins' => $wins, 'losses' => $losses, 'winpct' => $winpct];
        }

        return $result;
    }

    /**
     * Get aggregated HEAT wins and losses for all teams in bulk
     *
     * @return array<string, array{wins: int, losses: int, winpct: string}> Map of team name → HEAT totals
     */
    private function getAllHEATTotals(): array
    {
        $rows = $this->fetchAll(
            "SELECT currentname, SUM(wins) AS total_wins, SUM(losses) AS total_losses
            FROM ibl_heat_win_loss
            GROUP BY currentname"
        );

        /** @var array<string, array{wins: int, losses: int, winpct: string}> $result */
        $result = [];
        foreach ($rows as $row) {
            /** @var array{currentname: string, total_wins: int|null, total_losses: int|null} $row */
            $wins = $row['total_wins'] ?? 0;
            $losses = $row['total_losses'] ?? 0;
            $totalGames = $wins + $losses;
            $winpct = $totalGames > 0
                ? number_format($wins / $totalGames, 3)
                : '.000';
            $result[$row['currentname']] = ['wins' => $wins, 'losses' => $losses, 'winpct' => $winpct];
        }

        return $result;
    }

    /**
     * Get all title counts for all teams in a single pivot query
     *
     * @return array<string, array{heat_titles: int, div_titles: int, conf_titles: int, ibl_titles: int}> Map of team name → title counts
     */
    private function getAllTitleCounts(): array
    {
        $rows = $this->fetchAll(
            "SELECT
                name,
                SUM(CASE WHEN Award LIKE '%HEAT%' THEN 1 ELSE 0 END) AS heat_titles,
                SUM(CASE WHEN Award LIKE '%Division%' THEN 1 ELSE 0 END) AS div_titles,
                SUM(CASE WHEN Award LIKE '%Conference%' THEN 1 ELSE 0 END) AS conf_titles,
                SUM(CASE WHEN Award LIKE '%IBL Champions%' THEN 1 ELSE 0 END) AS ibl_titles
            FROM ibl_team_awards
            GROUP BY name"
        );

        /** @var array<string, array{heat_titles: int, div_titles: int, conf_titles: int, ibl_titles: int}> $result */
        $result = [];
        foreach ($rows as $row) {
            /** @var array{name: string, heat_titles: int, div_titles: int, conf_titles: int, ibl_titles: int} $row */
            $result[$row['name']] = [
                'heat_titles' => (int) $row['heat_titles'],
                'div_titles' => (int) $row['div_titles'],
                'conf_titles' => (int) $row['conf_titles'],
                'ibl_titles' => (int) $row['ibl_titles'],
            ];
        }

        return $result;
    }
}
