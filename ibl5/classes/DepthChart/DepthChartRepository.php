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
        
        // Use a single UPDATE statement to update all depth chart columns at once
        // This is more efficient and handles the case where values don't change
        // (MySQL returns 0 affected rows when updating to the same value, which is not an error)
        try {
            $this->execute(
                "UPDATE ibl_plr SET 
                    dc_PGDepth = ?, 
                    dc_SGDepth = ?, 
                    dc_SFDepth = ?, 
                    dc_PFDepth = ?, 
                    dc_CDepth = ?, 
                    dc_active = ?, 
                    dc_minutes = ?, 
                    dc_of = ?, 
                    dc_df = ?, 
                    dc_oi = ?, 
                    dc_di = ?, 
                    dc_bh = ? 
                WHERE name = ?",
                "iiiiiiiiiiiis",
                $pg,
                $sg,
                $sf,
                $pf,
                $c,
                $active,
                $min,
                $of,
                $df,
                $oi,
                $di,
                $bh,
                $playerName
            );
            
            // Success: the query executed without throwing an exception
            // Note: We don't check affected_rows because it returns 0 when values don't change
            return true;
        } catch (\RuntimeException $e) {
            // If an exception was thrown, the query failed (e.g., player doesn't exist)
            return false;
        }
    }
    
    /**
     * @see DepthChartRepositoryInterface::updateTeamHistory()
     */
    public function updateTeamHistory(string $teamName): bool
    {
        // Update both depth and sim_depth timestamps in a single statement
        // We don't check affected_rows because MySQL returns 0 when NOW() equals the existing timestamp
        try {
            $this->execute(
                "UPDATE ibl_team_history SET depth = NOW(), sim_depth = NOW() WHERE team_name = ?",
                "s",
                $teamName
            );
            
            // Success: the query executed without throwing an exception
            return true;
        } catch (\RuntimeException $e) {
            // If an exception was thrown, the query failed (e.g., team doesn't exist)
            return false;
        }
    }
}
