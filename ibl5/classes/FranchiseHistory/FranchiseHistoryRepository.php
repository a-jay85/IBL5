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

        // Dynamically calculate title counts from ibl_team_awards table
        foreach ($teams as &$team) {
            $teamName = $team['team_name'];
            $team['heat_titles'] = $this->getNumberOfTitles($teamName, 'HEAT');
            $team['div_titles'] = $this->getNumberOfTitles($teamName, 'Division');
            $team['conf_titles'] = $this->getNumberOfTitles($teamName, 'Conference');
            $team['ibl_titles'] = $this->getNumberOfTitles($teamName, 'IBL Champions');
        }

        /** @var array<int, FranchiseRow> $teams */
        return $teams;
    }

    /**
     * Get the number of titles for a team by title type
     *
     * Queries the ibl_team_awards table to count awards matching the team and title pattern.
     *
     * @param string $teamName Team name to look up
     * @param string $titleName Award name to search for (uses LIKE pattern)
     * @return int Number of titles matching the criteria
     */
    private function getNumberOfTitles(string $teamName, string $titleName): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(name) as count FROM ibl_team_awards WHERE name = ? AND Award LIKE ?",
            "ss",
            $teamName,
            "%{$titleName}%"
        );

        if ($result === null) {
            return 0;
        }

        /** @var int|string $count */
        $count = $result['count'] ?? 0;

        return (int) $count;
    }
}
