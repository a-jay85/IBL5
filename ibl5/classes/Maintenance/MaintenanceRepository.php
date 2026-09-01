<?php

declare(strict_types=1);

namespace Maintenance;

use League\League;
use Maintenance\Contracts\MaintenanceRepositoryInterface;

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
            "SELECT team_name FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND ?",
            "i",
            League::MAX_REAL_TEAMID
        );
    }

    /**
     * @see MaintenanceRepositoryInterface::getTeamSeasonRecords()
     *
     * No game-count predicate is applied. The 81..83 band that previously lived
     * here was removed when the ibl_team_win_loss view's dedup key was fixed in
     * ADR-0109: phantom boxscores no longer inflate game counts past 82, and the
     * 2004 anomaly rows (Aces 83 / Jazz 83 / Heat 81 / Suns 81) were remediated
     * at source. In-progress-season detection (year === currentSeasonYear AND
     * games < 82) and anomaly validation (games !== 82 → abort) are done by
     * LeagueControlPanelProcessor, not here.
     *
     * Fetches $limit + 1 rows so the processor can drop one in-progress season
     * and still have a full window of $limit complete seasons.
     *
     * @return array<int, array{year: int, wins: int, losses: int}>
     */
    public function getTeamSeasonRecords(string $teamName, int $currentSeasonYear, int $limit = 5): array
    {
        /** @var array<int, array{year: int, wins: int, losses: int}> */
        return $this->fetchAll(
            "SELECT year, wins, losses FROM `ibl_team_win_loss`
             WHERE currentname = ? AND year <= ?
             ORDER BY year DESC
             LIMIT ?",
            "sii",
            $teamName,
            $currentSeasonYear,
            $limit + 1
        );
    }

    /**
     * @see MaintenanceRepositoryInterface::updateTeamTradition()
     */
    public function updateTeamTradition(string $teamName, int $avgWins, int $avgLosses): bool
    {
        $this->execute(
            "UPDATE `ibl_team_info` SET contract_avg_w = ?, contract_avg_l = ? WHERE team_name = ?",
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
            "SELECT value FROM `ibl_settings` WHERE setting_key = ? AND league = 'ibl'",
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
