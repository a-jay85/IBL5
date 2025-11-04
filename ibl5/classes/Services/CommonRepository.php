<?php

namespace Services;

/**
 * CommonRepository - Centralized repository for common database queries
 * 
 * This class consolidates frequently used database operations that were
 * duplicated across multiple repository classes, following the DRY principle.
 * 
 * Responsibilities:
 * - User lookup operations
 * - Team lookup operations
 * - Player lookup operations
 * - Common data retrieval patterns
 */
class CommonRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Gets complete user information by username
     * 
     * @param string $username Username to look up
     * @return array|null User information or null if not found
     */
    public function getUserByUsername(string $username): ?array
    {
        $usernameEscaped = DatabaseService::escapeString($this->db, $username);
        $query = "SELECT * FROM nuke_users WHERE username = '$usernameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Gets the team name associated with a username
     * 
     * @param string $username Username to look up
     * @return string|null Team name if found, "Free Agents" if username is empty, or null if username not found
     */
    public function getTeamnameFromUsername(string $username): ?string
    {
        if (empty($username)) {
            return "Free Agents";
        }
        
        $usernameEscaped = DatabaseService::escapeString($this->db, $username);
        $query = "SELECT user_ibl_team FROM nuke_users WHERE username = '$usernameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_result($result, 0, 'user_ibl_team');
    }

    /**
     * Gets complete team information by team name
     * 
     * @param string $teamName Team name to look up
     * @return array|null Team information or null if not found
     */
    public function getTeamByName(string $teamName): ?array
    {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_team_info WHERE team_name = '$teamNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Gets team ID from team name
     * 
     * @param string $teamName Team name to look up
     * @return int|null Team ID or null if not found
     */
    public function getTidFromTeamname(string $teamName): ?int
    {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT teamid FROM ibl_team_info WHERE team_name = '$teamNameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'teamid');
    }

    /**
     * Gets team name from team ID
     * 
     * @param int $teamID Team ID to look up
     * @return string|null Team name or null if not found
     */
    public function getTeamnameFromTeamID(int $teamID): ?string
    {
        $teamID = (int) $teamID;
        $query = "SELECT team_name FROM ibl_team_info WHERE teamid = $teamID LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_result($result, 0, 'team_name');
    }

    /**
     * Gets Discord ID for a team
     * 
     * @param string $teamName Team name to look up
     * @return string|null Discord ID or null if not found
     */
    public function getTeamDiscordID(string $teamName): ?string
    {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT discordID FROM ibl_team_info WHERE team_name = '$teamNameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_result($result, 0, 'discordID');
    }

    /**
     * Gets complete player information by player ID
     * 
     * @param int $playerID Player ID to look up
     * @return array|null Player information or null if not found
     */
    public function getPlayerByID(int $playerID): ?array
    {
        $playerID = (int) $playerID;
        $query = "SELECT * FROM ibl_plr WHERE pid = $playerID";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Gets player ID from player name
     * 
     * @param string $playerName Player name to look up
     * @return int|null Player ID or null if not found
     */
    public function getPlayerIDFromPlayerName(string $playerName): ?int
    {
        $playerNameEscaped = DatabaseService::escapeString($this->db, $playerName);
        $query = "SELECT pid FROM ibl_plr WHERE name = '$playerNameEscaped' LIMIT 1";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, 'pid');
    }

    /**
     * Gets complete player information by player name
     * 
     * @param string $playerName Player name to look up
     * @return array|null Player information or null if not found
     */
    public function getPlayerByName(string $playerName): ?array
    {
        $playerNameEscaped = DatabaseService::escapeString($this->db, $playerName);
        $query = "SELECT * FROM ibl_plr WHERE name = '$playerNameEscaped'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return $this->db->sql_fetchrow($result);
    }

    /**
     * Gets total salary for a team for the current year
     * 
     * @param string $teamName Team name
     * @return int Total salary in thousands
     */
    public function getTeamTotalSalary(string $teamName): int
    {
        $teamNameEscaped = DatabaseService::escapeString($this->db, $teamName);
        $query = "SELECT * FROM ibl_plr WHERE teamname = '$teamNameEscaped' AND retired = 0";
        $result = $this->db->sql_query($query);
        
        if (!$result) {
            return 0;
        }
        
        $totalSalary = 0;
        $numPlayers = $this->db->sql_numrows($result);
        
        for ($i = 0; $i < $numPlayers; $i++) {
            $row = $this->db->sql_fetchrow($result);
            $cy = (int) $row['cy'];
            $contractYearField = "cy$cy";
            if (isset($row[$contractYearField])) {
                $totalSalary += (int) $row[$contractYearField];
            }
        }
        
        return $totalSalary;
    }
}
