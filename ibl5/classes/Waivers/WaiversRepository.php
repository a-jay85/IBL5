<?php

namespace Waivers;

/**
 * Handles database operations for waiver wire transactions
 */
class WaiversRepository
{
    private $db;
    private $commonRepository;
    private $newsService;
    
    public function __construct($db)
    {
        $this->db = $db;
        $this->commonRepository = new \Services\CommonRepository($db);
        $this->newsService = new \Services\NewsService($db);
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
     * @param array $team Team data array with 'teamname' and 'teamid' keys
     * @param array $contractData Contract data including salary, contractYearField, and contractYear
     * @return bool Success status
     */
    public function signPlayerFromWaivers(int $playerID, array $team, array $contractData): bool
    {
        $playerID = (int) $playerID;
        $teamName = $team['teamname'] ?? '';
        $teamID = (int) ($team['teamid'] ?? 0);
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        
        $query = "UPDATE ibl_plr
                  SET `ordinal` = '800',
                      `bird` = 0, ";
        
        if (isset($contractData['salary']) && $contractData['salary'] > 0) {
            $salary = (int) $contractData['salary'];
            $contractYearField = $contractData['contractYearField'] ?? 'cy1';
            $contractYear = $contractData['contractYear'] ?? 1;
            
            $query .= "`$contractYearField` = $salary,
                       `cy` = $contractYear, ";
        }
        
        $query .= "`teamname` = '$teamNameEscaped',
                   `tid` = $teamID,
                   `droptime` = 0
                   WHERE `pid` = $playerID
                   LIMIT 1";
        
        return $this->db->sql_query($query) !== false;
    }
}
