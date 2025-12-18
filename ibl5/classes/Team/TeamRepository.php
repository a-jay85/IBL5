<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamRepositoryInterface;

/**
 * @see TeamRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class TeamRepository extends \BaseMysqliRepository implements TeamRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see TeamRepositoryInterface::getTeam()
     */
    public function getTeam(int $teamID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_team_info WHERE teamid = ?",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamPowerData()
     */
    public function getTeamPowerData(string $teamName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_power WHERE Team = ?",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getDivisionStandings()
     */
    public function getDivisionStandings(string $division): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_power WHERE Division = ? ORDER BY gb DESC",
            "s",
            $division
        );
    }

    /**
     * @see TeamRepositoryInterface::getConferenceStandings()
     */
    public function getConferenceStandings(string $conference): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_power WHERE Conference = ? ORDER BY gb DESC",
            "s",
            $conference
        );
    }

    /**
     * @see TeamRepositoryInterface::getChampionshipBanners()
     */
    public function getChampionshipBanners(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_banners WHERE currentname = ? ORDER BY year ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getGMHistory()
     */
    public function getGMHistory(string $ownerName, string $teamName): array
    {
        $ownerAwardCode = $ownerName . " (" . $teamName . ")";
        return $this->fetchAll(
            "SELECT * FROM ibl_gm_history WHERE name LIKE ? ORDER BY year ASC",
            "s",
            $ownerAwardCode
        );
    }

    /**
     * @see TeamRepositoryInterface::getTeamAccomplishments()
     */
    public function getTeamAccomplishments(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_team_awards WHERE name LIKE ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getRegularSeasonHistory()
     */
    public function getRegularSeasonHistory(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_team_win_loss WHERE currentname = ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getHEATHistory()
     */
    public function getHEATHistory(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_heat_win_loss WHERE currentname = ? ORDER BY year DESC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     */
    public function getPlayoffResults(): array
    {
        return $this->fetchAll("SELECT * FROM ibl_playoff_results ORDER BY year DESC");
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgencyRoster()
     */
    public function getFreeAgencyRoster(int $teamID): array
    {
        return $this->fetchAll(
            "SELECT * 
            FROM ibl_plr 
            WHERE tid = ? 
              AND retired = 0 
              AND cyt != cy 
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getRosterUnderContract()
     */
    public function getRosterUnderContract(int $teamID): array
    {
        return $this->fetchAll(
            "SELECT * 
            FROM ibl_plr 
            WHERE tid = ? 
              AND retired = 0 
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC",
            "i",
            $teamID
        );
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgents()
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): array
    {
        if ($includeFreeAgencyActive) {
            return $this->fetchAll(
                "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC"
            );
        } else {
            return $this->fetchAll(
                "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC"
            );
        }
    }

    /**
     * @see TeamRepositoryInterface::getEntireLeagueRoster()
     */
    public function getEntireLeagueRoster(): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC"
        );
    }

    /**
     * @see TeamRepositoryInterface::getHistoricalRoster()
     */
    public function getHistoricalRoster(int $teamID, string $year): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE teamid = ? AND year = ? ORDER BY name ASC",
            "is",
            $teamID,
            $year
        );
    }
}
