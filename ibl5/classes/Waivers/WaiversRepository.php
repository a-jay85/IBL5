<?php

namespace Waivers;

use Season;
use Waivers\Contracts\WaiversRepositoryInterface;

/**
 * @see WaiversRepositoryInterface
 */
class WaiversRepository implements WaiversRepositoryInterface
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
     * @see WaiversRepositoryInterface::dropPlayerToWaivers()
     */
    public function dropPlayerToWaivers(int $playerID, int $timestamp): bool
    {
        if (method_exists($this->db, 'sql_escape_string')) {
            // Legacy MySQL abstraction layer
            $playerID = (int) $playerID;
            $timestamp = (int) $timestamp;
            
            $query = "UPDATE ibl_plr 
                      SET `ordinal` = '1000', 
                          `droptime` = '$timestamp' 
                      WHERE `pid` = $playerID 
                      LIMIT 1";
            
            return $this->db->sql_query($query) !== false;
        } else {
            // Modern mysqli - prepared statements
            $query = "UPDATE ibl_plr SET `ordinal` = '1000', `droptime` = ? WHERE `pid` = ? LIMIT 1";
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ii', $timestamp, $playerID);
            return $stmt->execute();
        }
    }
    
    /**
     * @see WaiversRepositoryInterface::signPlayerFromWaivers()
     */
    public function signPlayerFromWaivers(int $playerID, array $team, array $contractData): bool
    {
        $teamName = $team['team_name'] ?? '';
        $teamID = (int) ($team['teamid'] ?? 0);
        
        if (method_exists($this->db, 'sql_escape_string')) {
            // Legacy MySQL abstraction layer
            $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
            
            if (!$contractData['hasExistingContract']) {
                $queryContractSection = "`cy` = 0,
                                         `cyt` = 1,
                                         `cy1` = " . (int) $contractData['salary'] . ",
                                         `cy2` = 0,
                                         `cy3` = 0,
                                         `cy4` = 0,
                                         `cy5` = 0,
                                         `cy6` = 0, ";
            } else {
                $queryContractSection = '';
            }

            $query = "UPDATE ibl_plr
                      SET `ordinal` = '800',
                          `bird` = 0, ";
            $query .= $queryContractSection;
            $query .= "   `teamname` = '$teamNameEscaped',
                          `tid` = $teamID,
                          `droptime` = 0
                      WHERE `pid` = $playerID
                      LIMIT 1";
            
            return $this->db->sql_query($query) !== false;
        } else {
            // Modern mysqli - prepared statements
            if (!$contractData['hasExistingContract']) {
                // Need to set contract fields when no existing contract
                $salary = (int) $contractData['salary'];
                $query = "UPDATE ibl_plr 
                          SET `ordinal` = '800', `bird` = 0, `cy` = 0, `cyt` = 1, 
                              `cy1` = ?, `cy2` = 0, `cy3` = 0, `cy4` = 0, `cy5` = 0, 
                              `cy6` = 0, `teamname` = ?, `tid` = ?, `droptime` = 0 
                          WHERE `pid` = ? LIMIT 1";
                $stmt = $this->db->prepare($query);
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('isii', $salary, $teamName, $teamID, $playerID);
                return $stmt->execute();
            } else {
                // Keep existing contract
                $query = "UPDATE ibl_plr 
                          SET `ordinal` = '800', `bird` = 0, `teamname` = ?, `tid` = ?, 
                              `droptime` = 0 
                          WHERE `pid` = ? LIMIT 1";
                $stmt = $this->db->prepare($query);
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('sii', $teamName, $teamID, $playerID);
                return $stmt->execute();
            }
        }
    }
}
