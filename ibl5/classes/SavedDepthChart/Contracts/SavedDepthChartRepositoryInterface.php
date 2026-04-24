<?php

declare(strict_types=1);

namespace SavedDepthChart\Contracts;

/**
 * Interface for saved depth chart persistence
 *
 * Player ratings are NOT stored in saved depth charts. For analytics,
 * join with ibl_hist on pid + season_year to get position/skill ratings.
 *
 * @phpstan-type SavedDepthChartRow array{
 *     id: int,
 *     teamid: int,
 *     username: string,
 *     name: string|null,
 *     phase: string,
 *     season_year: int,
 *     sim_start_date: string,
 *     sim_end_date: string|null,
 *     sim_number_start: int,
 *     sim_number_end: int|null,
 *     is_active: int,
 *     created_at: string,
 *     updated_at: string
 * }
 *
 * @phpstan-type SavedDepthChartPlayerRow array{
 *     id: int,
 *     depth_chart_id: int,
 *     pid: int,
 *     player_name: string,
 *     ordinal: int,
 *     dc_pg_depth: int,
 *     dc_sg_depth: int,
 *     dc_sf_depth: int,
 *     dc_pf_depth: int,
 *     dc_c_depth: int,
 *     dc_can_play_in_game: int,
 *     dc_minutes: int,
 *     dc_of: int,
 *     dc_df: int,
 *     dc_oi: int,
 *     dc_di: int,
 *     dc_bh: int
 * }
 *
 * @phpstan-type PlayerSnapshotData array{
 *     pid: int,
 *     player_name: string,
 *     ordinal: int,
 *     dc_pg_depth: int,
 *     dc_sg_depth: int,
 *     dc_sf_depth: int,
 *     dc_pf_depth: int,
 *     dc_c_depth: int,
 *     dc_can_play_in_game: int,
 *     dc_minutes: int,
 *     dc_of: int,
 *     dc_df: int,
 *     dc_oi: int,
 *     dc_di: int,
 *     dc_bh: int
 * }
 */
interface SavedDepthChartRepositoryInterface
{
    /**
     * Create a new saved depth chart header row
     *
     * @return int Insert ID
     */
    public function createSavedDepthChart(
        int $teamid,
        string $username,
        ?string $name,
        string $phase,
        int $seasonYear,
        string $simStartDate,
        int $simNumberStart
    ): int;

    /**
     * Batch insert player snapshots for a saved depth chart
     *
     * @param list<PlayerSnapshotData> $playerSnapshots
     */
    public function saveDepthChartPlayers(int $depthChartId, array $playerSnapshots): void;

    /**
     * Deactivate all active depth charts for a team, setting end dates
     */
    public function deactivateForTeam(int $teamid, string $simEndDate, int $simNumberEnd): void;

    /**
     * Deactivate all active depth charts for a team EXCEPT the given ID
     */
    public function deactivateOthersForTeam(int $teamid, int $excludeId, string $simEndDate, int $simNumberEnd): void;

    /**
     * Get all saved depth charts for a team, ordered by created_at DESC
     *
     * @return list<SavedDepthChartRow>
     */
    public function getSavedDepthChartsForTeam(int $teamid): array;

    /**
     * Get a single saved depth chart by ID with team authorization check
     *
     * @return SavedDepthChartRow|null
     */
    public function getSavedDepthChartById(int $id, int $teamid): ?array;

    /**
     * Get all player rows for a saved depth chart
     *
     * @return list<SavedDepthChartPlayerRow>
     */
    public function getPlayersForDepthChart(int $depthChartId): array;

    /**
     * Rename a saved depth chart
     */
    public function updateName(int $id, int $teamid, string $newName): bool;

    /**
     * Replace all player settings for an existing saved depth chart
     *
     * @param list<PlayerSnapshotData> $playerSnapshots
     */
    public function updateDepthChartPlayers(int $depthChartId, array $playerSnapshots): void;

    /**
     * Bulk extend all active depth charts with new end dates
     *
     * @return int Number of rows updated
     */
    public function extendActiveDepthCharts(string $newEndDate, int $newSimNumber): int;

    /**
     * Re-activate a depth chart by ID (set is_active = 1)
     */
    public function reactivate(int $id, int $teamid): bool;

    /**
     * Get the most recent saved depth chart for a team
     *
     * @return SavedDepthChartRow|null
     */
    public function getMostRecentDepthChart(int $teamid): ?array;

    /**
     * Get live roster depth chart settings from ibl_plr
     *
     * Returns all dc_* columns plus name and ordinal for active players on the team.
     * This allows comparison with saved depth chart snapshots and building snapshots from live data.
     *
     * @return list<array{
     *     pid: int,
     *     name: string,
     *     ordinal: int,
     *     dc_pg_depth: int,
     *     dc_sg_depth: int,
     *     dc_sf_depth: int,
     *     dc_pf_depth: int,
     *     dc_c_depth: int,
     *     dc_can_play_in_game: int,
     *     dc_minutes: int,
     *     dc_of: int,
     *     dc_df: int,
     *     dc_oi: int,
     *     dc_di: int,
     *     dc_bh: int
     * }>
     */
    public function getLiveRosterSettings(int $teamid): array;

    /**
     * Get the active (is_active = 1) depth chart for a team
     *
     * @return SavedDepthChartRow|null
     */
    public function getActiveDepthChartForTeam(int $teamid): ?array;
}
