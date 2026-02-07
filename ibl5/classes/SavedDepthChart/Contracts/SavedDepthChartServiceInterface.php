<?php

declare(strict_types=1);

namespace SavedDepthChart\Contracts;

/**
 * Interface for saved depth chart business logic
 *
 * Player ratings are NOT stored in saved depth charts. For analytics,
 * join with ibl_hist on pid + season_year to get position/skill ratings.
 *
 * @phpstan-import-type SavedDepthChartRow from SavedDepthChartRepositoryInterface
 * @phpstan-import-type SavedDepthChartPlayerRow from SavedDepthChartRepositoryInterface
 * @phpstan-import-type PlayerSnapshotData from SavedDepthChartRepositoryInterface
 */
interface SavedDepthChartServiceInterface
{
    /**
     * Save or update a depth chart on form submission
     *
     * If $loadedDcId > 0: update that DC's player settings
     * If $loadedDcId === 0: deactivate previous active DC, create new one
     *
     * @param list<array<string, mixed>> $rosterPlayers Current roster from ibl_plr
     * @param array<string, mixed> $postData POST data from the form
     * @return int The saved depth chart ID
     */
    public function saveOnSubmit(
        int $tid,
        string $username,
        ?string $name,
        array $rosterPlayers,
        array $postData,
        int $loadedDcId,
        \Season $season
    ): int;

    /**
     * Load a saved depth chart with players and roster comparison
     *
     * @param list<int> $currentRosterPids PIDs currently on the team
     * @return array{
     *     depthChart: SavedDepthChartRow,
     *     players: list<SavedDepthChartPlayerRow>,
     *     currentRosterPids: list<int>,
     *     tradedPids: list<int>,
     *     newPlayerPids: list<int>
     * }|null
     */
    public function loadSavedDepthChart(int $id, int $tid, array $currentRosterPids): ?array;

    /**
     * Get win-loss record for a team in a date range
     *
     * @return array{wins: int, losses: int}
     */
    public function getWinLossRecord(int $tid, string $startDate, string $endDate): array;

    /**
     * Build label for the "Current (Live)" dropdown entry
     *
     * Shows phase, phase-specific sim number, date range, and win-loss record.
     */
    public function buildCurrentLiveLabel(int $tid, \Season $season): string;

    /**
     * Get formatted dropdown options for a team's saved depth charts
     *
     * @return list<array{id: int, label: string, isActive: bool}>
     */
    public function getDropdownOptions(int $tid, \Season $season): array;

    /**
     * Build a player snapshot from roster data and depth chart POST values
     *
     * Only captures DC settings (dc_* columns). Player ratings can be
     * retrieved by joining with ibl_hist on pid + season_year.
     *
     * @param array<string, mixed> $rosterPlayer Row from ibl_plr
     * @param array<string, int> $dcSettings Depth chart settings from POST
     * @return PlayerSnapshotData
     */
    public function buildPlayerSnapshot(array $rosterPlayer, array $dcSettings, int $ordinal): array;

    /**
     * Name or create the active depth chart for a team
     *
     * If an active DC exists, renames it. If not, creates a new saved DC
     * from live ibl_plr values with the given name.
     *
     * @return array{success: bool, id: int, name: string}|array{success: bool, error: string}
     */
    public function nameOrCreateActive(int $tid, string $username, string $name, \Season $season): array;
}
