<?php

declare(strict_types=1);

namespace Services;

/**
 * CommonMysqliRepository - Centralized repository for common database queries using mysqli
 * 
 * This class consolidates frequently used database operations that were
 * duplicated across multiple repository classes, following the DRY principle.
 * 
 * Uses prepared statements for all queries to prevent SQL injection.
 * Extends BaseMysqliRepository for standardized query execution.
 * 
 * Responsibilities:
 * - User lookup operations
 * - Team lookup operations
 * - Player lookup operations
 * - Common data retrieval patterns
 */
class CommonMysqliRepository extends \BaseMysqliRepository
{
    /**
     * Gets complete user information by username
     * 
     * @param string $username Username to look up
     * @return array|null User information or null if not found
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM nuke_users WHERE username = ?",
            "s",
            $username
        );
    }

    /**
     * Gets the team name associated with a username
     * 
     * @param string|null $username Username to look up (nullable)
     * @return string|null Team name if found, "Free Agents" if username is empty, or null if username not found
     */
    public function getTeamnameFromUsername(?string $username): ?string
    {
        if (empty($username)) {
            return "Free Agents";
        }
        
        $result = $this->fetchOne(
            "SELECT user_ibl_team FROM nuke_users WHERE username = ? LIMIT 1",
            "s",
            $username
        );
        
        return $result ? ($result['user_ibl_team'] ?? null) : null;
    }

    /**
     * Gets complete team information by team name
     * 
     * @param string $teamName Team name to look up
     * @return array|null Team information or null if not found
     */
    public function getTeamByName(string $teamName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_team_info WHERE team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * Gets team ID from team name
     * 
     * @param string $teamName Team name to look up
     * @return int|null Team ID or null if not found
     */
    public function getTidFromTeamname(string $teamName): ?int
    {
        $result = $this->fetchOne(
            "SELECT teamid FROM ibl_team_info WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
        
        return $result ? (int) ($result['teamid'] ?? 0) : null;
    }

    /**
     * Gets team name from team ID
     * 
     * @param int $teamID Team ID to look up
     * @return string|null Team name or null if not found
     */
    public function getTeamnameFromTeamID(int $teamID): ?string
    {
        $result = $this->fetchOne(
            "SELECT team_name FROM ibl_team_info WHERE teamid = ? LIMIT 1",
            "i",
            $teamID
        );
        
        return $result ? ($result['team_name'] ?? null) : null;
    }

    /**
     * Gets Discord ID for a team
     * 
     * @param string $teamName Team name to look up
     * @return int|null Discord ID or null if not found
     */
    public function getTeamDiscordID(string $teamName): int
    {
        $result = $this->fetchOne(
            "SELECT discordID FROM ibl_team_info WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
        
        return $result ? (int) ($result['discordID'] ?? 0) : null;
    }

    /**
     * Gets complete player information by player ID
     * 
     * @param int $playerID Player ID to look up
     * @return array|null Player information or null if not found
     */
    public function getPlayerByID(int $playerID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ?",
            "i",
            $playerID
        );
    }

    /**
     * Gets player ID from player name
     * 
     * @param string $playerName Player name to look up
     * @return int|null Player ID or null if not found
     */
    public function getPlayerIDFromPlayerName(string $playerName): ?int
    {
        $result = $this->fetchOne(
            "SELECT pid FROM ibl_plr WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );
        
        return $result ? (int) ($result['pid'] ?? 0) : null;
    }

    /**
     * Gets complete player information by player name
     * 
     * @param string $playerName Player name to look up
     * @return array|null Player information or null if not found
     */
    public function getPlayerByName(string $playerName): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * Gets total salary for a team for the current year
     * 
     * @param string $teamName Team name
     * @return int Total salary in thousands
     */
    public function getTeamTotalSalary(string $teamName): int
    {
        $players = $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE teamname = ? AND retired = 0",
            "s",
            $teamName
        );
        
        $totalSalary = 0;
        foreach ($players as $player) {
            $cy = (int) ($player['cy'] ?? 0);
            $contractYearField = "cy$cy";
            if (isset($player[$contractYearField])) {
                $totalSalary += (int) $player[$contractYearField];
            }
        }
        
        return $totalSalary;
    }
}
