<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartRepositoryInterface;

/**
 * @see DepthChartRepositoryInterface
 */
class DepthChartRepository implements DepthChartRepositoryInterface
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @see DepthChartRepositoryInterface::getPlayersOnTeam()
     */
    public function getPlayersOnTeam(string $teamName, int $teamID)
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        $teamID = (int) $teamID;
        $query = "SELECT * FROM ibl_plr WHERE teamname = '$teamNameEscaped' AND tid = $teamID AND retired = '0' AND ordinal <= " . \JSB::WAIVERS_ORDINAL . " ORDER BY ordinal ASC";
        return $this->db->sql_query($query);
    }
    
    /**
     * @see DepthChartRepositoryInterface::updatePlayerDepthChart()
     */
    public function updatePlayerDepthChart(string $playerName, array $depthChartValues): bool
    {
        $playerNameEscaped = \Services\DatabaseService::escapeString($this->db, $playerName);
        
        $pg = (int) $depthChartValues['pg'];
        $sg = (int) $depthChartValues['sg'];
        $sf = (int) $depthChartValues['sf'];
        $pf = (int) $depthChartValues['pf'];
        $c = (int) $depthChartValues['c'];
        $active = (int) $depthChartValues['active'];
        $min = (int) $depthChartValues['min'];
        $of = (int) $depthChartValues['of'];
        $df = (int) $depthChartValues['df'];
        $oi = (int) $depthChartValues['oi'];
        $di = (int) $depthChartValues['di'];
        $bh = (int) $depthChartValues['bh'];
        
        $queries = [
            "UPDATE ibl_plr SET dc_PGDepth = $pg WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_SGDepth = $sg WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_SFDepth = $sf WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_PFDepth = $pf WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_CDepth = $c WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_active = $active WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_minutes = $min WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_of = $of WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_df = $df WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_oi = $oi WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_di = $di WHERE name = '$playerNameEscaped'",
            "UPDATE ibl_plr SET dc_bh = $bh WHERE name = '$playerNameEscaped'"
        ];
        
        foreach ($queries as $query) {
            if (!$this->db->sql_query($query)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * @see DepthChartRepositoryInterface::updateTeamHistory()
     */
    public function updateTeamHistory(string $teamName): bool
    {
        $teamNameEscaped = \Services\DatabaseService::escapeString($this->db, $teamName);
        
        $queries = [
            "UPDATE ibl_team_history SET depth = NOW() WHERE team_name = '$teamNameEscaped'",
            "UPDATE ibl_team_history SET sim_depth = NOW() WHERE team_name = '$teamNameEscaped'"
        ];
        
        foreach ($queries as $query) {
            if (!$this->db->sql_query($query)) {
                return false;
            }
        }
        
        return true;
    }
}
