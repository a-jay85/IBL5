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
    /**
     * @see SavedDepthChartRepositoryInterface::createSavedDepthChart()
     */
    public function createSavedDepthChart(
        int $tid,
        string $username,
        ?string $name,
        string $phase,
        int $seasonYear,
        string $simStartDate,
        int $simNumberStart
    ): int {
        if ($name !== null) {
            $this->execute(
                "INSERT INTO ibl_saved_depth_charts
                    (tid, username, name, phase, season_year, sim_start_date, sim_number_start, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                "isssissi",
                $tid,
                $username,
                $name,
                $phase,
                $seasonYear,
                $simStartDate,
                $simNumberStart
            );
        } else {
            $this->execute(
                "INSERT INTO ibl_saved_depth_charts
                    (tid, username, phase, season_year, sim_start_date, sim_number_start, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                "issisi",
                $tid,
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
                "INSERT INTO ibl_saved_depth_chart_players
                    (depth_chart_id, pid, player_name, ordinal,
                     dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth,
                     dc_active, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "iisiiiiiiiiiiiii",
                $depthChartId,
                $snapshot['pid'],
                $snapshot['player_name'],
                $snapshot['ordinal'],
                $snapshot['dc_PGDepth'],
                $snapshot['dc_SGDepth'],
                $snapshot['dc_SFDepth'],
                $snapshot['dc_PFDepth'],
                $snapshot['dc_CDepth'],
                $snapshot['dc_active'],
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
    public function deactivateForTeam(int $tid, string $simEndDate, int $simNumberEnd): void
    {
        $this->execute(
            "UPDATE ibl_saved_depth_charts
             SET is_active = 0, sim_end_date = ?, sim_number_end = ?
             WHERE tid = ? AND is_active = 1",
            "sii",
            $simEndDate,
            $simNumberEnd,
            $tid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getSavedDepthChartsForTeam()
     * @return list<SavedDepthChartRow>
     */
    public function getSavedDepthChartsForTeam(int $tid): array
    {
        /** @var list<SavedDepthChartRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_saved_depth_charts WHERE tid = ? ORDER BY created_at DESC",
            "i",
            $tid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getSavedDepthChartById()
     * @return SavedDepthChartRow|null
     */
    public function getSavedDepthChartById(int $id, int $tid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_saved_depth_charts WHERE id = ? AND tid = ? LIMIT 1",
            "ii",
            $id,
            $tid
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
            "SELECT * FROM ibl_saved_depth_chart_players WHERE depth_chart_id = ? ORDER BY ordinal ASC",
            "i",
            $depthChartId
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::updateName()
     */
    public function updateName(int $id, int $tid, string $newName): bool
    {
        $affected = $this->execute(
            "UPDATE ibl_saved_depth_charts SET name = ? WHERE id = ? AND tid = ?",
            "sii",
            $newName,
            $id,
            $tid
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
            "DELETE FROM ibl_saved_depth_chart_players WHERE depth_chart_id = ?",
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
            "UPDATE ibl_saved_depth_charts SET sim_end_date = ?, sim_number_end = ? WHERE is_active = 1",
            "si",
            $newEndDate,
            $newSimNumber
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::reactivate()
     */
    public function reactivate(int $id, int $tid): bool
    {
        $affected = $this->execute(
            "UPDATE ibl_saved_depth_charts SET is_active = 1 WHERE id = ? AND tid = ?",
            "ii",
            $id,
            $tid
        );
        return $affected > 0;
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getMostRecentDepthChart()
     * @return SavedDepthChartRow|null
     */
    public function getMostRecentDepthChart(int $tid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_saved_depth_charts WHERE tid = ? ORDER BY created_at DESC LIMIT 1",
            "i",
            $tid
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getLiveRosterSettings()
     * @return list<array{pid: int, name: string, ordinal: int, dc_PGDepth: int, dc_SGDepth: int, dc_SFDepth: int, dc_PFDepth: int, dc_CDepth: int, dc_active: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}>
     */
    public function getLiveRosterSettings(int $tid): array
    {
        /** @var list<array{pid: int, name: string, ordinal: int, dc_PGDepth: int, dc_SGDepth: int, dc_SFDepth: int, dc_PFDepth: int, dc_CDepth: int, dc_active: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> */
        return $this->fetchAll(
            "SELECT pid, name, ordinal, dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth,
                    dc_active, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh
             FROM ibl_plr
             WHERE tid = ? AND retired = '0' AND ordinal <= ?
             ORDER BY ordinal ASC",
            "ii",
            $tid,
            \JSB::WAIVERS_ORDINAL
        );
    }

    /**
     * @see SavedDepthChartRepositoryInterface::getActiveDepthChartForTeam()
     * @return SavedDepthChartRow|null
     */
    public function getActiveDepthChartForTeam(int $tid): ?array
    {
        /** @var SavedDepthChartRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_saved_depth_charts WHERE tid = ? AND is_active = 1 LIMIT 1",
            "i",
            $tid
        );
    }
}
