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
            "SELECT team_name FROM ibl_team_info WHERE teamid != ?",
            "i",
            \League::FREE_AGENTS_TEAMID
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
     * @see MaintenanceRepositoryInterface::updateDivisionTitles()
     */
    public function updateDivisionTitles(): bool
    {
        $this->execute(
            "UPDATE ibl_team_history SET div_titles = (
                SELECT COUNT(*) FROM ibl_team_awards
                WHERE ibl_team_awards.Award LIKE '%Div.%'
                AND ibl_team_history.team_name = ibl_team_awards.name
            )",
            ""
        );

        return true;
    }

    /**
     * @see MaintenanceRepositoryInterface::updateConferenceTitles()
     */
    public function updateConferenceTitles(): bool
    {
        $this->execute(
            "UPDATE ibl_team_history SET conf_titles = (
                SELECT COUNT(*) FROM ibl_team_awards
                WHERE ibl_team_awards.Award LIKE '%Conf.%'
                AND ibl_team_history.team_name = ibl_team_awards.name
            )",
            ""
        );

        return true;
    }

    /**
     * @see MaintenanceRepositoryInterface::updateIblTitles()
     */
    public function updateIblTitles(): bool
    {
        $this->execute(
            "UPDATE ibl_team_history SET ibl_titles = (
                SELECT COUNT(*) FROM ibl_team_awards
                WHERE ibl_team_awards.Award LIKE '%World%'
                AND ibl_team_history.team_name = ibl_team_awards.name
            )",
            ""
        );

        return true;
    }

    /**
     * @see MaintenanceRepositoryInterface::updateHeatTitles()
     */
    public function updateHeatTitles(): bool
    {
        $this->execute(
            "UPDATE ibl_team_history SET heat_titles = (
                SELECT COUNT(*) FROM ibl_team_awards
                WHERE ibl_team_awards.Award LIKE '%H.E.A.T.%'
                AND ibl_team_history.team_name = ibl_team_awards.name
            )",
            ""
        );

        return true;
    }

    /**
     * @see MaintenanceRepositoryInterface::updatePlayoffAppearances()
     */
    public function updatePlayoffAppearances(): bool
    {
        $this->execute(
            "UPDATE ibl_team_history SET playoffs = (
                SELECT COUNT(*) FROM ibl_playoff_results
                WHERE (ibl_playoff_results.winner = ibl_team_history.team_name
                       AND ibl_playoff_results.round = '1')
                   OR (ibl_playoff_results.loser = ibl_team_history.team_name
                       AND ibl_playoff_results.round = '1')
            )",
            ""
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
