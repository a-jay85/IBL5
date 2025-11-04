<?php

namespace Team;

/**
 * TeamRepository - Handles all database operations related to teams
 * 
 * Following the Repository pattern, this class encapsulates all SQL queries
 * and database interactions for team-related data.
 */
class TeamRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get team power rankings data
     */
    public function getTeamPowerData($teamName)
    {
        $teamName = $this->db->sql_escape_string($teamName);
        $query = "SELECT * FROM ibl_power WHERE Team = '$teamName'";
        $result = $this->db->sql_query($query);
        
        if ($this->db->sql_numrows($result) > 0) {
            return $this->db->sql_fetch_assoc($result);
        }
        return null;
    }

    /**
     * Get division standings for a specific division
     */
    public function getDivisionStandings($division)
    {
        $division = $this->db->sql_escape_string($division);
        $query = "SELECT * FROM ibl_power WHERE Division = '$division' ORDER BY gb DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get conference standings for a specific conference
     */
    public function getConferenceStandings($conference)
    {
        $conference = $this->db->sql_escape_string($conference);
        $query = "SELECT * FROM ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get championship banners for a team
     */
    public function getChampionshipBanners($teamName)
    {
        $teamName = $this->db->sql_escape_string($teamName);
        $query = "SELECT * FROM ibl_banners WHERE currentname = '$teamName' ORDER BY year ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get GM history for a team
     */
    public function getGMHistory($ownerName, $teamName)
    {
        $ownerAwardCode = $this->db->sql_escape_string($ownerName . " (" . $teamName . ")");
        $query = "SELECT * FROM ibl_gm_history WHERE name LIKE '$ownerAwardCode' ORDER BY year ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get team accomplishments/awards
     */
    public function getTeamAccomplishments($teamName)
    {
        $teamName = $this->db->sql_escape_string($teamName);
        $query = "SELECT * FROM ibl_team_awards WHERE name LIKE '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get regular season win/loss history
     */
    public function getRegularSeasonHistory($teamName)
    {
        $teamName = $this->db->sql_escape_string($teamName);
        $query = "SELECT * FROM ibl_team_win_loss WHERE currentname = '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get HEAT tournament history
     */
    public function getHEATHistory($teamName)
    {
        $teamName = $this->db->sql_escape_string($teamName);
        $query = "SELECT * FROM ibl_heat_win_loss WHERE currentname = '$teamName' ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get playoff results for all teams
     */
    public function getPlayoffResults()
    {
        $query = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
        return $this->db->sql_query($query);
    }

    /**
     * Get team roster for free agency (players whose contract year doesn't match current year)
     */
    public function getFreeAgencyRoster($teamID)
    {
        $teamID = (int) $teamID;
        $query = "SELECT * 
            FROM ibl_plr 
            WHERE tid = '$teamID' 
              AND retired = 0 
              AND cyt != cy 
            ORDER BY name ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get team roster under contract
     */
    public function getRosterUnderContract($teamID)
    {
        $teamID = (int) $teamID;
        $query = "SELECT * 
            FROM ibl_plr 
            WHERE tid = '$teamID' 
              AND retired = 0 
            ORDER BY name ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get free agents (team 0, players with ordinal > 959)
     */
    public function getFreeAgents($includeFreeAgencyActive = false)
    {
        if ($includeFreeAgencyActive) {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
        } else {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC";
        }
        return $this->db->sql_query($query);
    }

    /**
     * Get entire league roster
     */
    public function getEntireLeagueRoster()
    {
        $query = "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC";
        return $this->db->sql_query($query);
    }

    /**
     * Get historical roster for a specific year
     */
    public function getHistoricalRoster($teamID, $year)
    {
        $teamID = (int) $teamID;
        $year = $this->db->sql_escape_string($year);
        $query = "SELECT * FROM ibl_hist WHERE teamid = '$teamID' AND year = '$year' ORDER BY name ASC";
        return $this->db->sql_query($query);
    }
}
