<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type DepthChartValues from Contracts\DepthChartEntryRepositoryInterface
 *
 * @see DepthChartEntryRepositoryInterface
 */
class DepthChartEntryRepository extends \BaseMysqliRepository implements DepthChartEntryRepositoryInterface
{
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see DepthChartEntryRepositoryInterface::getPlayersOnTeam()
     * @return list<PlayerRow>
     */
    public function getPlayersOnTeam(int $teamID): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE tid = ? AND retired = 0 AND ordinal <= ? ORDER BY ordinal ASC",
            "ii",
            $teamID,
            \JSB::WAIVERS_ORDINAL
        );
    }
    
    /**
     * @see DepthChartEntryRepositoryInterface::updatePlayerDepthChart()
     * @param DepthChartValues $depthChartValues
     */
    public function updatePlayerDepthChart(string $playerName, array $depthChartValues): bool
    {
        $pg = $depthChartValues['pg'];
        $sg = $depthChartValues['sg'];
        $sf = $depthChartValues['sf'];
        $pf = $depthChartValues['pf'];
        $c = $depthChartValues['c'];
        $active = $depthChartValues['canPlayInGame'];
        $min = $depthChartValues['min'];

        try {
            $this->execute(
                "UPDATE ibl_plr SET
                    dc_PGDepth = ?,
                    dc_SGDepth = ?,
                    dc_SFDepth = ?,
                    dc_PFDepth = ?,
                    dc_CDepth = ?,
                    dc_canPlayInGame = ?,
                    dc_minutes = ?,
                    dc_of = 0,
                    dc_df = 0,
                    dc_oi = 0,
                    dc_di = 0,
                    dc_bh = 0
                WHERE name = ?",
                "iiiiiiis",
                $pg,
                $sg,
                $sf,
                $pf,
                $c,
                $active,
                $min,
                $playerName
            );

            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }
    
    /**
     * @see DepthChartEntryRepositoryInterface::updateTeamHistory()
     */
    public function updateTeamHistory(string $teamName): bool
    {
        // Update both depth and sim_depth timestamps in a single statement
        // We don't check affected_rows because MySQL returns 0 when NOW() equals the existing timestamp
        try {
            $this->execute(
                "UPDATE ibl_team_info SET depth = NOW(), sim_depth = NOW() WHERE team_name = ?",
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
