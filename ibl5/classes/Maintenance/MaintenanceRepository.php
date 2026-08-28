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
     * @see MaintenanceRepositoryInterface::getTeamRecentCompleteSeasons()
     *
     * A "complete" season is 82 games, but the 81..83 band deliberately admits
     * the four known season-2004 anomalies in `ibl_team_win_loss` (Aces 83,
     * Jazz 83 — one re-simmed game double-counted by the view's dedup key;
     * Heat 81, Suns 81 — one scheduled game that was never simmed and is
     * unrecoverable). A scan of seasons 1998-2008 found those four rows and
     * nothing else outside 82, so the band admits exactly them. Without it the
     * four teams silently slid their 5-season window back to 2003 while the
     * other 24 used 2004, making tradition factors mutually inconsistent.
     *
     * KEEP THE UPPER BOUND. Phantom-boxscore bugs inflate a season to 119-127
     * games; an unbounded predicate would let that corruption into the average.
     *
     * @return array<int, array{wins: int, losses: int}>
     */
    public function getTeamRecentCompleteSeasons(string $teamName, int $limit = 5): array
    {
        /** @var array<int, array{wins: int, losses: int}> */
        return $this->fetchAll(
            "SELECT wins, losses FROM `ibl_team_win_loss`
             WHERE currentname = ? AND (wins + losses BETWEEN 81 AND 83)
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
