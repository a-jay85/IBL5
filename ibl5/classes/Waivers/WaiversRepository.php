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
}
