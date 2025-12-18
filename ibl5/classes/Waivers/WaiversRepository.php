<?php

declare(strict_types=1);

namespace Waivers;

use BaseMysqliRepository;
use Season;
use Waivers\Contracts\WaiversRepositoryInterface;

/**
 * @see WaiversRepositoryInterface
 */
class WaiversRepository extends BaseMysqliRepository implements WaiversRepositoryInterface
{
    /**
     * Constructor
     * 
     * @param object $db Active mysqli connection (or duck-typed mock for testing)
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }
    
    /**
     * @see WaiversRepositoryInterface::dropPlayerToWaivers()
     */
    public function dropPlayerToWaivers(int $playerID, int $timestamp): bool
    {
        $query = "UPDATE ibl_plr 
                  SET `ordinal` = '1000', 
                      `droptime` = ? 
                  WHERE `pid` = ? 
                  LIMIT 1";
        
        try {
            $affectedRows = $this->execute($query, 'ii', $timestamp, $playerID);
            return $affectedRows > 0;
        } catch (\RuntimeException $e) {
            error_log("Failed to drop player to waivers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * @see WaiversRepositoryInterface::signPlayerFromWaivers()
     */
    public function signPlayerFromWaivers(int $playerID, array $team, array $contractData): bool
    {
        $teamName = $team['team_name'] ?? '';
        $teamID = (int) ($team['teamid'] ?? 0);
        
        try {
            if (!$contractData['hasExistingContract']) {
                // Need to set contract fields when no existing contract
                $salary = (int) $contractData['salary'];
                $query = "UPDATE ibl_plr 
                          SET `ordinal` = '800', `bird` = 0, `cy` = 0, `cyt` = 1, 
                              `cy1` = ?, `cy2` = 0, `cy3` = 0, `cy4` = 0, `cy5` = 0, 
                              `cy6` = 0, `teamname` = ?, `tid` = ?, `droptime` = 0 
                          WHERE `pid` = ? LIMIT 1";
                $affectedRows = $this->execute($query, 'isii', $salary, $teamName, $teamID, $playerID);
            } else {
                // Keep existing contract
                $query = "UPDATE ibl_plr 
                          SET `ordinal` = '800', `bird` = 0, `teamname` = ?, `tid` = ?, 
                              `droptime` = 0 
                          WHERE `pid` = ? LIMIT 1";
                $affectedRows = $this->execute($query, 'sii', $teamName, $teamID, $playerID);
            }
            
            return $affectedRows > 0;
        } catch (\RuntimeException $e) {
            error_log("Failed to sign player from waivers: " . $e->getMessage());
            return false;
        }
    }
}
