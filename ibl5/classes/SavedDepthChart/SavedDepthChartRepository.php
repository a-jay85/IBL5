<?php

declare(strict_types=1);

namespace SavedDepthChart;

use SavedDepthChart\Contracts\SavedDepthChartRepositoryInterface;

/**
 * @phpstan-import-type SavedDepthChartRow from Contracts\SavedDepthChartRepositoryInterface
 * @phpstan-import-type SavedDepthChartPlayerRow from Contracts\SavedDepthChartRepositoryInterface
 * @phpstan-import-type PlayerSnapshotData from Contracts\SavedDepthChartRepositoryInterface
 *
 * @see SavedDepthChartRepositoryInterface
 */
class SavedDepthChartRepository extends \BaseMysqliRepository implements SavedDepthChartRepositoryInterface
{
    private string $headerTable;
    private string $playersTable;
    private string $plrTable;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->headerTable = $this->resolveTable('ibl_saved_depth_charts');
        $this->playersTable = $this->resolveTable('ibl_saved_depth_chart_players');
        $this->plrTable = $this->resolveTable('ibl_plr');
    }

    /**
     * @see SavedDepthChartRepositoryInterface::createSavedDepthChart()
     */
    public function createSavedDepthChart(
        int $teamid,
        string $username,
        ?string $name,
        string $phase,
        int $seasonYear,
        string $simStartDate,
        int $simNumberStart
    ): int {
        if ($name !== null) {
            $this->execute(
                "INSERT INTO {$this->headerTable}
                    (teamid, username, name, phase, season_year, sim_start_date, sim_number_start, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                "isssisi",
                $teamid,
                $username,
                $name,
                $phase,
                $seasonYear,
                $simStartDate,
                $simNumberStart
            );
        } else {
            $this->execute(
                "INSERT INTO {$this->headerTable}
                    (teamid, username, phase, season_year, sim_start_date, sim_number_start, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                "issisi",
                $teamid,
                $username,
                $phase,
                $seasonYear,
                $simStartDate,
                $simNumberStart
            );
        }

        return $this->getLastInsertId();
    }

    /**
     * @see SavedDepthChartRepositoryInterface::saveDepthChartPlayers()
     * @param list<PlayerSnapshotData> $playerSnapshots
     */
    public function saveDepthChartPlayers(int $depthChartId, array $playerSnapshots): void
    {
        foreach ($playerSnapshots as $snapshot) {
            $this->execute(
                "INSERT INTO {$this->playersTable}
                    (depth_chart_id, pid, player_name, ordinal,
                     dc_pg_depth, dc_sg_depth, dc_sf_depth, dc_pf_depth, dc_c_depth,
                     dc_can_play_in_game, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "iisiiiiiiiiiiiii",
                $depthChartId,
                $snapshot['pid'],
                $snapshot['player_name'],
                $snapshot['ordinal'],
                $snapshot['dc_pg_depth'],
                $snapshot['dc_sg_depth'],
                $snapshot['dc_sf_depth'],
                $snapshot['dc_pf_depth'],
                $snapshot['dc_c_depth'],
                $snapshot['dc_can_play_in_game'],
                $snapshot['dc_minutes'],
                $snapshot['dc_of'],
                $snapshot['dc_df'],
                $snapshot['dc_oi'],
                $snapshot['dc_di'],
                $snapshot['dc_bh']
            );
        }
    }

    /**
     * @see SavedDepthChartRepositoryInterface::deactivateForTeam()
     */
    public function deactivateForTeam(int $teamid, string $simEndDate, int $simNumberEnd): void
    {
        $this->execute(
            "UPDATE {$this->headerTable}
             SET is_active = 0, sim_end_date = ?, sim_number_end = ?
             WHERE teamid = ? AND is_active = 1",
            "sii",
            $simEndDate,
            $simNumberEnd,
            $teamid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::deactivateOthersForTeam()
     */
    public function deactivateOthersForTeam(int $teamid, int $excludeId, string $simEndDate, int $simNumberEnd): void
    {
        $this->execute(
            "UPDATE {$this->headerTable}
             SET is_active = 0, sim_end_date = ?, sim_number_end = ?
             WHERE teamid = ? AND is_active = 1 AND id != ?",
            "siii",
            $simEndDate,
            $simNumberEnd,
            $teamid,
            $excludeId
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getSavedDepthChartsForTeam()
     * @return list<SavedDepthChartRow>
     */
    public function getSavedDepthChartsForTeam(int $teamid): array
    {
        /** @var list<SavedDepthChartRow> */
        return $this->fetchAll(
            "SELECT * FROM {$this->headerTable} WHERE teamid = ? ORDER BY created_at DESC, id DESC",
            "i",
            $teamid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getSavedDepthChartById()
     * @return SavedDepthChartRow|null
     */
    public function getSavedDepthChartById(int $id, int $teamid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM {$this->headerTable} WHERE id = ? AND teamid = ? LIMIT 1",
            "ii",
            $id,
            $teamid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getPlayersForDepthChart()
     * @return list<SavedDepthChartPlayerRow>
     */
    public function getPlayersForDepthChart(int $depthChartId): array
    {
        /** @var list<SavedDepthChartPlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM {$this->playersTable} WHERE depth_chart_id = ? ORDER BY ordinal ASC",
            "i",
            $depthChartId
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::updateName()
     */
    public function updateName(int $id, int $teamid, string $newName): bool
    {
        $affected = $this->execute(
            "UPDATE {$this->headerTable} SET name = ? WHERE id = ? AND teamid = ?",
            "sii",
            $newName,
            $id,
            $teamid
        );
        return $affected > 0;
    }

    /**
     * @see SavedDepthChartRepositoryInterface::updateDepthChartPlayers()
     * @param list<PlayerSnapshotData> $playerSnapshots
     */
    public function updateDepthChartPlayers(int $depthChartId, array $playerSnapshots): void
    {
        $this->execute(
            "DELETE FROM {$this->playersTable} WHERE depth_chart_id = ?",
            "i",
            $depthChartId
        );

        $this->saveDepthChartPlayers($depthChartId, $playerSnapshots);
    }

    /**
     * @see SavedDepthChartRepositoryInterface::extendActiveDepthCharts()
     */
    public function extendActiveDepthCharts(string $newEndDate, int $newSimNumber): int
    {
        return $this->execute(
            "UPDATE {$this->headerTable} SET sim_end_date = ?, sim_number_end = ? WHERE is_active = 1",
            "si",
            $newEndDate,
            $newSimNumber
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::reactivate()
     */
    public function reactivate(int $id, int $teamid): bool
    {
        $affected = $this->execute(
            "UPDATE {$this->headerTable} SET is_active = 1 WHERE id = ? AND teamid = ?",
            "ii",
            $id,
            $teamid
        );
        return $affected > 0;
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getMostRecentDepthChart()
     * @return SavedDepthChartRow|null
     */
    public function getMostRecentDepthChart(int $teamid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM {$this->headerTable} WHERE teamid = ? ORDER BY created_at DESC, id DESC LIMIT 1",
            "i",
            $teamid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getLiveRosterSettings()
     * @return list<array{pid: int, name: string, ordinal: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>
     */
    public function getLiveRosterSettings(int $teamid): array
    {
        /** @var list<array{pid: int, name: string, ordinal: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> */
        return $this->fetchAll(
            "SELECT pid, name, ordinal, dc_pg_depth, dc_sg_depth, dc_sf_depth, dc_pf_depth, dc_c_depth,
                    dc_can_play_in_game, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh
             FROM {$this->plrTable}
             WHERE teamid = ? AND retired = '0' AND ordinal <= ?
             ORDER BY ordinal ASC",
            "ii",
            $teamid,
            \JSB::WAIVERS_ORDINAL
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getActiveDepthChartForTeam()
     * @return SavedDepthChartRow|null
     */
    public function getActiveDepthChartForTeam(int $teamid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM {$this->headerTable} WHERE teamid = ? AND is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 1",
            "i",
            $teamid
        );
    }
}
