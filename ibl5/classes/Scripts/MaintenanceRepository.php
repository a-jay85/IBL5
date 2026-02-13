<?php

declare(strict_types=1);

namespace Scripts;

use Scripts\Contracts\MaintenanceRepositoryInterface;

/**
 * MaintenanceRepository - Database operations for maintenance scripts
 *
 * Handles operations for tradition updates, franchise history, and settings.
 *
 * @see MaintenanceRepositoryInterface
 */
class MaintenanceRepository extends \BaseMysqliRepository implements MaintenanceRepositoryInterface
{
    /**
     * @see MaintenanceRepositoryInterface::getAllTeams()
     *
     * @return array<int, array{team_name: string}>
     */
    public function getAllTeams(): array
    {
        /** @var array<int, array{team_name: string}> */
        return $this->fetchAll(
            "SELECT team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND ?",
            "i",
            \League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see MaintenanceRepositoryInterface::getTeamRecentCompleteSeasons()
     *
     * @return array<int, array{wins: int, losses: int}>
     */
    public function getTeamRecentCompleteSeasons(string $teamName, int $limit = 5): array
    {
        /** @var array<int, array{wins: int, losses: int}> */
        return $this->fetchAll(
            "SELECT wins, losses FROM ibl_team_win_loss
             WHERE currentname = ? AND (wins + losses = 82)
             ORDER BY year DESC
             LIMIT ?",
            "si",
            $teamName,
            $limit
        );
    }

    /**
     * @see MaintenanceRepositoryInterface::updateTeamTradition()
     */
    public function updateTeamTradition(string $teamName, int $avgWins, int $avgLosses): bool
    {
        $this->execute(
            "UPDATE ibl_team_info SET Contract_AvgW = ?, Contract_AvgL = ? WHERE team_name = ?",
            "iis",
            $avgWins,
            $avgLosses,
            $teamName
        );

        return true;
    }

    /**
     * @see MaintenanceRepositoryInterface::getSetting()
     */
    public function getSetting(string $name): ?string
    {
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ?",
            "s",
            $name
        );

        if ($result === null) {
            return null;
        }

        /** @var string $value */
        $value = $result['value'];
        return $value;
    }
}
