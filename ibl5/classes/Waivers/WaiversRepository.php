<?php

namespace Waivers;

/**
 * Handles database operations for waiver wire transactions
 */
class WaiversRepository
{
    private $db;
    private $commonRepository;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new \Services\CommonRepository($db);
    }
    
    /**
     * Gets user information by username
     * 
     * @deprecated Use CommonRepository::getUserByUsername() instead
     * @param string $username Username to look up
     * @return array|null User information or null if not found
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->commonRepository->getUserByUsername($username);
    }
    
    /**
     * Gets team information by team name
     * 
     * @deprecated Use CommonRepository::getTeamByName() instead
     * @param string $teamName Team name to look up
     * @return array|null Team information or null if not found
     */
    public function getTeamByName(string $teamName): ?array
    {
        return $this->commonRepository->getTeamByName($teamName);
    }
    
    /**
     * Gets total salary for a team for the current year
     * 
     * @deprecated Use CommonRepository::getTeamTotalSalary() instead
     * @param string $teamName Team name
     * @return int Total salary in thousands
     */
    public function getTeamTotalSalary(string $teamName): int
    {
        return $this->commonRepository->getTeamTotalSalary($teamName);
    }
    
    /**
     * Gets player information by player ID
     * 
     * @deprecated Use CommonRepository::getPlayerByID() instead
     * @param int $playerID Player ID
     * @return array|null Player information or null if not found
     */
    public function getPlayerByID(int $playerID): ?array
    {
        return $this->commonRepository->getPlayerByID($playerID);
    }
    
    /**
     * Drops a player to waivers
     * 
     * @param int $playerID Player ID to drop
     * @param int $timestamp Current timestamp
     * @return bool Success status
     */
    public function dropPlayerToWaivers(int $playerID, int $timestamp): bool
    {
        $playerID = (int) $playerID;
        $timestamp = (int) $timestamp;
        
        $query = "UPDATE ibl_plr 
                  SET `ordinal` = '1000', 
                      `droptime` = '$timestamp' 
                  WHERE `pid` = $playerID 
                  LIMIT 1";
        
        return $this->db->sql_query($query) !== false;
    }
    
    /**
     * Signs a player from waivers
     * 
     * @param int $playerID Player ID to sign
     * @param string $teamName Team name
     * @param int $teamID Team ID
     * @param array $contractData Contract data including cy1, cy (optional)
     * @return bool Success status
     */
    public function signPlayerFromWaivers(int $playerID, string $teamName, int $teamID, array $contractData): bool
    {
        $playerID = (int) $playerID;
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $teamID = (int) $teamID;
        
        $query = "UPDATE ibl_plr
                  SET `ordinal` = '800',
                      `bird` = 0, ";
        
        if (isset($contractData['cy1']) && $contractData['cy1'] > 0) {
            $cy1 = (int) $contractData['cy1'];
            $query .= "`cy1` = $cy1,
                       `cy` = 1, ";
        }
        
        $query .= "`teamname` = '$teamNameEscaped',
                   `tid` = $teamID,
                   `droptime` = 0
                   WHERE `pid` = $playerID
                   LIMIT 1";
        
        return $this->db->sql_query($query) !== false;
    }
    
    /**
     * Gets the category ID for waiver pool moves
     * 
     * @return int|null Category ID or null if not found
     */
    public function getWaiverPoolMovesCategory(): ?int
    {
        $query = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
        $result = $this->db->sql_query($query);
        
        if (!$result || $this->db->sql_numrows($result) === 0) {
            return null;
        }
        
        return (int) $this->db->sql_result($result, 0, "catid");
    }
    
    /**
     * Increments the waiver pool moves counter
     * 
     * @return bool Success status
     */
    public function incrementWaiverPoolMovesCounter(): bool
    {
        $query = "UPDATE nuke_stories_cat 
                  SET counter = counter + 1 
                  WHERE title = 'Waiver Pool Moves'";
        
        return $this->db->sql_query($query) !== false;
    }
    
    /**
     * Creates a news story for a waiver transaction
     * 
     * @param int $topicID Topic ID
     * @param string $title Story title
     * @param string $hometext Story content
     * @return bool Success status
     */
    public function createNewsStory(int $topicID, string $title, string $hometext): bool
    {
        $topicID = (int) $topicID;
        $titleEscaped = \Services\DatabaseService::escapeString($this->db, $title);
        $hometextEscaped = \Services\DatabaseService::escapeString($this->db, $hometext);
        $timestamp = date('Y-m-d H:i:s', time());
        
        $query = "INSERT INTO nuke_stories
                  (catid,
                   aid,
                   title,
                   time,
                   hometext,
                   topic,
                   informant,
                   counter,
                   alanguage)
                  VALUES
                  (" . WaiversController::WAIVER_POOL_MOVES_CATEGORY_ID . ",
                   'Associated Press',
                   '$titleEscaped',
                   '$timestamp',
                   '$hometextEscaped',
                   $topicID,
                   'Associated Press',
                   0,
                   'english')";
        
        return $this->db->sql_query($query) !== false;
    }
}
