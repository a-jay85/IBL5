<?php

declare(strict_types=1);

namespace FranchiseHistory;

use FranchiseHistory\Contracts\FranchiseHistoryRepositoryInterface;

/**
 * FranchiseHistoryRepository - Data access layer for franchise history
 *
 * Retrieves franchise history and win/loss records from the database.
 *
 * @see FranchiseHistoryRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class FranchiseHistoryRepository extends \BaseMysqliRepository implements FranchiseHistoryRepositoryInterface
{
    /**
     * @see FranchiseHistoryRepositoryInterface::getAllFranchiseHistory()
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

        return $this->fetchAll(
            $query,
            "iii",
            \League::FREE_AGENTS_TEAMID,
            $fiveSeasonsAgoEndingYear,
            $currentEndingYear
        );
    }

    /**
     * @see FranchiseHistoryRepositoryInterface::getNumberOfTitles()
     */
    public function getNumberOfTitles(string $teamName, string $titleType): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_champions WHERE champion = ? AND title = ?",
            "ss",
            $teamName,
            $titleType
        );

        return (int)($result['count'] ?? 0);
    }
}
