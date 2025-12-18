<?php

declare(strict_types=1);

namespace DepthChart;

use DepthChart\Contracts\DepthChartRepositoryInterface;

/**
 * @see DepthChartRepositoryInterface
 * @extends \BaseMysqliRepository
 */
class DepthChartRepository extends \BaseMysqliRepository implements DepthChartRepositoryInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see DepthChartRepositoryInterface::getPlayersOnTeam()
     */
    public function getPlayersOnTeam(string $teamName, int $teamID): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE teamname = ? AND tid = ? AND retired = '0' AND ordinal <= ? ORDER BY ordinal ASC",
            "sii",
            $teamName,
            $teamID,
            \JSB::WAIVERS_ORDINAL
        );
    }
    
    /**
     * @see DepthChartRepositoryInterface::updatePlayerDepthChart()
     */
    public function updatePlayerDepthChart(string $playerName, array $depthChartValues): bool
    {
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
        
        // Execute each update as a prepared statement
        $updates = [
            ['dc_PGDepth', $pg],
            ['dc_SGDepth', $sg],
            ['dc_SFDepth', $sf],
            ['dc_PFDepth', $pf],
            ['dc_CDepth', $c],
            ['dc_active', $active],
            ['dc_minutes', $min],
            ['dc_of', $of],
            ['dc_df', $df],
            ['dc_oi', $oi],
            ['dc_di', $di],
            ['dc_bh', $bh]
        ];
        
        foreach ($updates as [$column, $value]) {
            $affected = $this->execute(
                "UPDATE ibl_plr SET $column = ? WHERE name = ?",
                "is",
                $value,
                $playerName
            );
            
            if ($affected === 0) {
                // If no rows were affected, the player might not exist
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
        // Execute both updates with prepared statements
        $affected1 = $this->execute(
            "UPDATE ibl_team_history SET depth = NOW() WHERE team_name = ?",
            "s",
            $teamName
        );
        
        $affected2 = $this->execute(
            "UPDATE ibl_team_history SET sim_depth = NOW() WHERE team_name = ?",
            "s",
            $teamName
        );
        
        // Return true if at least one update succeeded
        return ($affected1 > 0 || $affected2 > 0);
    }
}
