<?php

namespace RookieOption;

/**
 * Handles database operations for rookie option transactions
 */
class RookieOptionRepository
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Updates a player's rookie option contract year
     * 
     * @param int $playerID Player ID
     * @param int $draftRound Draft round (1 or 2)
     * @param int $extensionAmount Contract extension amount
     * @return bool Success status
     */
    public function updatePlayerRookieOption(int $playerID, int $draftRound, int $extensionAmount): bool
    {
        $playerID = (int) $playerID;
        $extensionAmount = (int) $extensionAmount;
        
        // First round picks get year 4, second round picks get year 3
        $contractYear = ($draftRound == 1) ? 'cy4' : 'cy3';
        
        $query = "UPDATE ibl_plr 
                  SET `$contractYear` = $extensionAmount 
                  WHERE pid = $playerID";
        
        return $this->db->sql_query($query) !== false;
    }
}
