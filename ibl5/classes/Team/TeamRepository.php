<?php

declare(strict_types=1);

namespace Team;

use Services\DatabaseService;
use Team\Contracts\TeamRepositoryInterface;

/**
 * @see TeamRepositoryInterface
 */
class TeamRepository implements TeamRepositoryInterface
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see TeamRepositoryInterface::getTeamPowerData()
     */
    public function getTeamPowerData(string $teamName): ?array
    {
        $teamName = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_power WHERE Team = '$teamName'";
        $result = $this->db->sql_query($query);
        
        if ($this->db->sql_numrows($result) > 0) {
            return $this->db->sql_fetch_assoc($result);
        }
        return null;
    }

    /**
     * @see TeamRepositoryInterface::getDivisionStandings()
     */
    public function getDivisionStandings(string $division): mixed
    {
        $division = DatabaseService::escapeString($this->db, $division);
        $query = "SELECT * FROM ibl_power WHERE Division = '$division' ORDER BY gb DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getConferenceStandings()
     */
    public function getConferenceStandings(string $conference): mixed
    {
        $conference = DatabaseService::escapeString($this->db, $conference);
        $query = "SELECT * FROM ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getChampionshipBanners()
     */
    public function getChampionshipBanners(string $teamName): mixed
    {
        $teamName = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_banners WHERE currentname = '$teamName' ORDER BY year ASC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getGMHistory()
     */
    public function getGMHistory(string $ownerName, string $teamName): mixed
    {
        $ownerAwardCode = DatabaseService::escapeString($this->db, $ownerName . " (" . $teamName . ")");
        $query = "SELECT * FROM ibl_gm_history WHERE name LIKE '$ownerAwardCode' ORDER BY year ASC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getTeamAccomplishments()
     */
    public function getTeamAccomplishments(string $teamName): mixed
    {
        $teamName = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_team_awards WHERE name LIKE '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getRegularSeasonHistory()
     */
    public function getRegularSeasonHistory(string $teamName): mixed
    {
        $teamName = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_team_win_loss WHERE currentname = '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getHEATHistory()
     */
    public function getHEATHistory(string $teamName): mixed
    {
        $teamName = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_heat_win_loss WHERE currentname = '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getPlayoffResults()
     */
    public function getPlayoffResults(): mixed
    {
        $query = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgencyRoster()
     */
    public function getFreeAgencyRoster(int $teamID): mixed
    {
        $teamID = (int) $teamID;
        $query = "SELECT * 
            FROM ibl_plr 
            WHERE tid = '$teamID' 
              AND retired = 0 
              AND cyt != cy 
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getRosterUnderContract()
     */
    public function getRosterUnderContract(int $teamID): mixed
    {
        $teamID = (int) $teamID;
        $query = "SELECT * 
            FROM ibl_plr 
            WHERE tid = '$teamID' 
              AND retired = 0 
            ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getFreeAgents()
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): mixed
    {
        if ($includeFreeAgencyActive) {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
        } else {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC";
        }
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getEntireLeagueRoster()
     */
    public function getEntireLeagueRoster(): mixed
    {
        $query = "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC";
        return $this->db->sql_query($query);
    }

    /**
     * @see TeamRepositoryInterface::getHistoricalRoster()
     */
    public function getHistoricalRoster(int $teamID, string $year): mixed
    {
        $teamID = (int) $teamID;

        $year = DatabaseService::escapeString($this->db, $year);
        $query = "SELECT * FROM ibl_hist WHERE teamid = '$teamID' AND year = '$year' ORDER BY name ASC";
        return $this->db->sql_query($query);
    }
}
