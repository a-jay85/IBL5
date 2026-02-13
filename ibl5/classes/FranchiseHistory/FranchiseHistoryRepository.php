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

        // Query 1: All-time totals from vw_franchise_summary (no redundant ibl_team_win_loss JOIN)
        /** @var list<array{teamid: int, team_name: string, color1: string, color2: string, totwins: int, totloss: int, winpct: string, playoffs: int, div_titles: int, conf_titles: int, ibl_titles: int, heat_titles: int}> $summaryRows */
        $summaryRows = $this->fetchAll(
            "SELECT ti.teamid, ti.team_name, ti.color1, ti.color2,
                    fs.totwins, fs.totloss, fs.winpct, fs.playoffs,
                    fs.div_titles, fs.conf_titles, fs.ibl_titles, fs.heat_titles
             FROM ibl_team_info ti
             JOIN vw_franchise_summary fs ON fs.teamid = ti.teamid
             WHERE ti.teamid <> ?
             ORDER BY ti.teamid ASC",
            "i",
            \League::FREE_AGENTS_TEAMID
        );

        // Query 2: Rolling 5-season window from ibl_team_win_loss directly (avoids double materialization)
        /** @var list<array{currentname: string, five_season_wins: int, five_season_losses: int}> $windowRows */
        $windowRows = $this->fetchAll(
            "SELECT currentname,
                    CAST(SUM(wins) AS UNSIGNED) AS five_season_wins,
                    CAST(SUM(losses) AS UNSIGNED) AS five_season_losses
             FROM ibl_team_win_loss
             WHERE year BETWEEN ? AND ?
             GROUP BY currentname",
            "ii",
            $fiveSeasonsAgoEndingYear,
            $currentEndingYear
        );

        /** @var array<string, array{five_season_wins: int, five_season_losses: int}> $windowByTeam */
        $windowByTeam = [];
        foreach ($windowRows as $row) {
            $windowByTeam[$row['currentname']] = [
                'five_season_wins' => $row['five_season_wins'],
                'five_season_losses' => $row['five_season_losses'],
            ];
        }

        // Bulk-fetch playoff totals and HEAT totals
        $allPlayoffTotals = $this->getAllPlayoffTotals();
        $allHeatTotals = $this->getAllHEATTotals();

        /** @var array<int, FranchiseRow> $teams */
        $teams = [];
        foreach ($summaryRows as $summary) {
            $teamName = $summary['team_name'];
            $window = $windowByTeam[$teamName] ?? ['five_season_wins' => 0, 'five_season_losses' => 0];
            $fiveWins = $window['five_season_wins'];
            $fiveLosses = $window['five_season_losses'];
            $totalGames = $fiveWins + $fiveLosses;
            $fiveWinpct = $totalGames > 0
                ? number_format($fiveWins / $totalGames, 3)
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
     * Get aggregated playoff game wins and losses for all teams in bulk
     *
     * Uses a single SELECT from vw_playoff_series_results with conditional aggregation
     * to compute both winner and loser game tallies without UNION ALL (avoids double materialization).
     *
     * @return array<string, array{wins: int, losses: int, winpct: string}> Map of team name -> playoff totals
     */
    private function getAllPlayoffTotals(): array
    {
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

        /** @var array<string, array{wins: int, losses: int, winpct: string}> $result */
        $result = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, total_wins: int, total_losses: int} $row */
            $wins = $row['total_wins'];
            $losses = $row['total_losses'];
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

}
