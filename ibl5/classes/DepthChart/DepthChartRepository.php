<?php

namespace DepthChart;

/**
 * Handles database operations for depth chart data
 */
class DepthChartRepository
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Gets offensive sets for a team
     * 
     * @param string $teamName Team name
     * @return mixed Database result
     */
    public function getOffenseSets(string $teamName)
    {
        $sql = "SELECT * FROM ibl_offense_sets WHERE TeamName = '$teamName' ORDER BY SetNumber ASC";
        return $this->db->sql_query($sql);
    }
    
    /**
     * Gets players on a team
     * 
     * @param string $teamName Team name
     * @param int $teamID Team ID
     * @return mixed Database result
     */
    public function getPlayersOnTeam(string $teamName, int $teamID)
    {
        $query = "SELECT * FROM ibl_plr WHERE teamname = '$teamName' AND tid = $teamID AND retired = '0' AND ordinal <= " . \JSB::WAIVERS_ORDINAL . " ORDER BY ordinal ASC";
        return $this->db->sql_query($query);
    }
    
    /**
     * Gets a specific offensive set
     * 
     * @param string $teamName Team name
     * @param int $setNumber Set number (1-3)
     * @return array Offensive set data
     */
    public function getOffenseSet(string $teamName, int $setNumber): array
    {
        $query = "SELECT * FROM ibl_offense_sets WHERE TeamName = '$teamName' AND SetNumber = '$setNumber'";
        $result = $this->db->sql_query($query);
        return $this->db->sql_fetchrow($result);
    }
    
    /**
     * Updates player depth chart data
     * 
     * @param string $playerName Player name
     * @param array $depthChartValues Array of depth chart values
     * @return bool Success status
     */
    public function updatePlayerDepthChart(string $playerName, array $depthChartValues): bool
    {
        $playerName = addslashes($playerName);
        
        $queries = [
            "UPDATE ibl_plr SET dc_PGDepth = '{$depthChartValues['pg']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_SGDepth = '{$depthChartValues['sg']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_SFDepth = '{$depthChartValues['sf']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_PFDepth = '{$depthChartValues['pf']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_CDepth = '{$depthChartValues['c']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_active = '{$depthChartValues['active']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_minutes = '{$depthChartValues['min']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_of = '{$depthChartValues['of']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_df = '{$depthChartValues['df']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_oi = '{$depthChartValues['oi']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_di = '{$depthChartValues['di']}' WHERE name = '$playerName'",
            "UPDATE ibl_plr SET dc_bh = '{$depthChartValues['bh']}' WHERE name = '$playerName'"
        ];
        
        foreach ($queries as $query) {
            if (!$this->db->sql_query($query)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Updates team history with depth chart submission timestamps
     * 
     * @param string $teamName Team name
     * @return bool Success status
     */
    public function updateTeamHistory(string $teamName): bool
    {
        $queries = [
            "UPDATE ibl_team_history SET depth = NOW() WHERE team_name = '$teamName'",
            "UPDATE ibl_team_history SET sim_depth = NOW() WHERE team_name = '$teamName'"
        ];
        
        foreach ($queries as $query) {
            if (!$this->db->sql_query($query)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Gets board configuration
     * 
     * @return array Board configuration
     */
    public function getBoardConfig(): array
    {
        $sql = "SELECT * FROM nuke_bbconfig";
        $result = $this->db->sql_query($sql);
        $config = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $config[$row['config_name']] = $row['config_value'];
        }
        return $config;
    }
}
