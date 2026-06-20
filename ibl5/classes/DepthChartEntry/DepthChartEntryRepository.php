<?php

declare(strict_types=1);

namespace DepthChartEntry;

use DepthChartEntry\Contracts\DepthChartEntryRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type DepthChartValues from Contracts\DepthChartEntryRepositoryInterface
 *
 * @see DepthChartEntryRepositoryInterface
 */
class DepthChartEntryRepository extends \BaseMysqliRepository implements DepthChartEntryRepositoryInterface
{
    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('db').
     */
    private \Psr\Log\LoggerInterface $channelLogger;

    public function __construct(\mysqli $db, ?\Psr\Log\LoggerInterface $logger = null)
    {
        parent::__construct($db);
        $this->channelLogger = $logger ?? \Logging\LoggerFactory::getChannel('db');
    }

    /**
     * @see DepthChartEntryRepositoryInterface::getPlayersOnTeam()
     * @return list<PlayerRow>
     */
    public function getPlayersOnTeam(int $teamid): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM `ibl_plr` WHERE teamid = ? AND retired = 0 AND ordinal <= ? ORDER BY ordinal ASC",
            "ii",
            $teamid,
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
                "UPDATE `ibl_plr` SET
                    dc_pg_depth = ?,
                    dc_sg_depth = ?,
                    dc_sf_depth = ?,
                    dc_pf_depth = ?,
                    dc_c_depth = ?,
                    dc_can_play_in_game = ?,
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
            $this->channelLogger->error('updatePlayerDepthChart failed', [
                'exception' => $e,
                'context' => ['playerName' => $playerName],
            ]);
            return false;
        }
    }

    /**
     * @see DepthChartEntryRepositoryInterface::updateTeamHistory()
     */
    public function updateTeamHistory(string $teamName): bool
    {
        try {
            $this->execute(
                "UPDATE `ibl_team_info` SET depth = NOW(), sim_depth = NOW() WHERE team_name = ?",
                "s",
                $teamName
            );

            return true;
        } catch (\RuntimeException $e) {
            $this->channelLogger->error('updateTeamHistory failed', [
                'exception' => $e,
                'context' => ['teamName' => $teamName],
            ]);
            return false;
        }
    }
}
