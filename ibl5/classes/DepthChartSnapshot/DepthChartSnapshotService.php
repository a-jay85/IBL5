<?php

declare(strict_types=1);

namespace DepthChartSnapshot;

use DepthChartSnapshot\Contracts\DepthChartSnapshotServiceInterface;
use DepthChartSnapshot\Contracts\DepthChartSnapshotRepositoryInterface;
use DepthChartSnapshot\Contracts\DepthChartLabelBuilderInterface;
use DepthChartSnapshot\Contracts\SlotAssignmentResolverInterface;
use Season\Season;
use BasketballStats\StatsSanitizer;

/**
 * @phpstan-import-type SavedDepthChartRow from Contracts\DepthChartSnapshotRepositoryInterface
 * @phpstan-import-type SavedDepthChartPlayerRow from Contracts\DepthChartSnapshotRepositoryInterface
 * @phpstan-import-type PlayerSnapshotData from Contracts\DepthChartSnapshotRepositoryInterface
 *
 * @see DepthChartSnapshotServiceInterface
 */
class DepthChartSnapshotService implements DepthChartSnapshotServiceInterface
{
    private DepthChartSnapshotRepositoryInterface $repository;
    private \mysqli $db;
    private SlotAssignmentResolverInterface $slotResolver;
    private DepthChartLabelBuilderInterface $labelBuilder;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;

    /**
     * Per-teamid memo of getActiveDepthChartForTeam() within one request.
     * Stores null too — array_key_exists() distinguishes "not fetched"
     * from "fetched, no active DC".
     *
     * @var array<int, SavedDepthChartRow|null>
     */
    private array $activeDcCache = [];

    public function __construct(
        \mysqli $db,
        ?DepthChartSnapshotRepositoryInterface $repo = null,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?SlotAssignmentResolverInterface $slotResolver = null,
        ?DepthChartLabelBuilderInterface $labelBuilder = null
    ) {
        $this->db = $db;
        $this->repository = $repo ?? new DepthChartSnapshotRepository($db);
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->slotResolver = $slotResolver ?? new SlotAssignmentResolver();
        $this->labelBuilder = $labelBuilder ?? new DepthChartLabelBuilder();
    }

    /**
     * @see DepthChartSnapshotServiceInterface::saveOnSubmit()
     * @param list<array<string, mixed>> $rosterPlayers
     * @param array<string, mixed> $postData
     */
    public function saveOnSubmit(
        int $teamid,
        string $username,
        ?string $name,
        array $rosterPlayers,
        array $postData,
        int $loadedDcId,
        Season $season
    ): int {
        $snapshots = $this->buildAllSnapshots($rosterPlayers, $postData);

        $this->db->begin_transaction();
        try {
            if ($loadedDcId > 0) {
                $existingDc = $this->repository->getSavedDepthChartById($loadedDcId, $teamid);
                if ($existingDc !== null) {
                    $this->repository->updateDepthChartPlayers($loadedDcId, $snapshots);

                    $this->repository->deactivateOthersForTeam($teamid, $loadedDcId, $season->lastSimEndDate, $season->lastSimNumber);
                    if ($existingDc['is_active'] === 0) {
                        $this->repository->reactivate($loadedDcId, $teamid);
                    }

                    $this->db->commit();

                    $this->logger->info('depth_chart_saved', [
                        'action' => 'depth_chart_saved',
                        'dc_id' => $loadedDcId,
                        'team_id' => $teamid,
                        'dc_name' => $name,
                        'phase' => $season->phase,
                    ]);

                    return $loadedDcId;
                }
            }

            // Check if most recent DC is unused (no sim has consumed it yet)
            $mostRecent = $this->repository->getMostRecentDepthChart($teamid);
            if ($mostRecent !== null && $mostRecent['sim_end_date'] === null) {
                // Unused DC exists — update it instead of creating a new one
                $this->repository->updateDepthChartPlayers($mostRecent['id'], $snapshots);

                // Ensure it's the only active DC
                $this->repository->deactivateOthersForTeam($teamid, $mostRecent['id'], $season->lastSimEndDate, $season->lastSimNumber);
                if ($mostRecent['is_active'] === 0) {
                    $this->repository->reactivate($mostRecent['id'], $teamid);
                }

                $this->db->commit();

                $this->logger->info('depth_chart_saved', [
                    'action' => 'depth_chart_saved',
                    'dc_id' => $mostRecent['id'],
                    'team_id' => $teamid,
                    'dc_name' => $name,
                    'phase' => $season->phase,
                ]);

                return $mostRecent['id'];
            }

            // No unused DC — create new one
            $this->repository->deactivateForTeam($teamid, $season->lastSimEndDate, $season->lastSimNumber);

            $simStartDate = $this->calculateNextSimStartDate($season->lastSimEndDate);
            $simNumberStart = $season->lastSimNumber + 1;

            $dcId = $this->repository->createSavedDepthChart(
                $teamid,
                $username,
                $name,
                $season->phase,
                $season->endingYear,
                $simStartDate,
                $simNumberStart
            );

            $this->repository->saveDepthChartPlayers($dcId, $snapshots);

            $this->db->commit();

            $this->logger->info('depth_chart_saved', [
                'action' => 'depth_chart_saved',
                'dc_id' => $dcId,
                'team_id' => $teamid,
                'dc_name' => $name,
                'phase' => $season->phase,
            ]);

            return $dcId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * @see DepthChartSnapshotServiceInterface::loadSavedDepthChart()
     * @param list<int> $currentRosterPids
     */
    public function loadSavedDepthChart(int $id, int $teamid, array $currentRosterPids): ?array
    {
        $dc = $this->repository->getSavedDepthChartById($id, $teamid);
        if ($dc === null) {
            return null;
        }

        $players = $this->repository->getPlayersForDepthChart($id);
        $savedPids = array_map(
            static fn(array $p): int => $p['pid'],
            $players
        );

        $tradedPids = array_values(array_diff($savedPids, $currentRosterPids));
        $newPlayerPids = array_values(array_diff($currentRosterPids, $savedPids));

        return [
            'depthChart' => $dc,
            'players' => $players,
            'currentRosterPids' => $currentRosterPids,
            'tradedPids' => $tradedPids,
            'newPlayerPids' => $newPlayerPids,
        ];
    }

    /**
     * @see DepthChartSnapshotServiceInterface::getWinLossRecord()
     * @return array{wins: int, losses: int}
     */
    public function getWinLossRecord(int $teamid, string $startDate, string $endDate): array
    {
        return $this->repository->getWinLossRecord($teamid, $startDate, $endDate);
    }

    /**
     * Build label for the "Current (Live)" dropdown entry
     *
     * Delegates string assembly to DepthChartLabelBuilderInterface::buildLiveLabel().
     */
    public function buildCurrentLiveLabel(int $teamid, Season $season): string
    {
        $activeDc = $this->getActiveDc($teamid);
        $record = $activeDc !== null
            ? $this->getWinLossRecord($teamid, $activeDc['sim_start_date'], $season->lastSimEndDate)
            : null;

        return $this->labelBuilder->buildLiveLabel($activeDc, $season, $record);
    }

    /**
     * @see DepthChartSnapshotServiceInterface::getDropdownOptions()
     * @return list<array{id: int, label: string, isActive: bool}>
     */
    public function getDropdownOptions(int $teamid, Season $season): array
    {
        $savedDcs = $this->repository->getSavedDepthChartsForTeam($teamid);

        // Find active DC (most recently updated if multiple)
        $activeDc = $this->getActiveDc($teamid);

        // Hide active DC if it matches live ibl_plr settings exactly
        $hideActiveDc = false;
        if ($activeDc !== null) {
            $dcPlayers = $this->repository->getPlayersForDepthChart($activeDc['id']);
            $liveRosterPlayers = $this->repository->getLiveRosterSettings($teamid);
            $hideActiveDc = $this->isDepthChartMatchingLive($dcPlayers, $liveRosterPlayers);
        }

        $options = [];
        foreach ($savedDcs as $dc) {
            // Skip active DC if it matches live settings
            if ($hideActiveDc && $activeDc !== null && $dc['id'] === $activeDc['id']) {
                continue;
            }

            // Ended DCs count through their own end date; the open DC counts through the last sim
            $recordEndDate = $dc['sim_end_date'] ?? $season->lastSimEndDate;
            $record = $this->getWinLossRecord($teamid, $dc['sim_start_date'], $recordEndDate);
            $label = $this->labelBuilder->buildDropdownLabel($dc, $season, $record);
            $options[] = [
                'id' => $dc['id'],
                'label' => $label,
                'isActive' => $dc['is_active'] === 1,
            ];
        }

        return $options;
    }

    /**
     * @see DepthChartSnapshotServiceInterface::buildPlayerSnapshot()
     * @param array<string, mixed> $rosterPlayer
     * @param array<string, int> $dcSettings
     * @return PlayerSnapshotData
     */
    public function buildPlayerSnapshot(array $rosterPlayer, array $dcSettings, int $ordinal): array
    {
        return [
            'pid' => StatsSanitizer::toInt($rosterPlayer['pid'] ?? 0),
            'player_name' => $this->toString($rosterPlayer['name'] ?? ''),
            'ordinal' => $ordinal,
            'dc_pg_depth' => $dcSettings['pg'] ?? 0,
            'dc_sg_depth' => $dcSettings['sg'] ?? 0,
            'dc_sf_depth' => $dcSettings['sf'] ?? 0,
            'dc_pf_depth' => $dcSettings['pf'] ?? 0,
            'dc_c_depth' => $dcSettings['c'] ?? 0,
            'dc_can_play_in_game' => $dcSettings['canPlayInGame'] ?? 0,
            'dc_minutes' => $dcSettings['min'] ?? 0,
            'dc_of' => $dcSettings['of'] ?? 0,
            'dc_df' => $dcSettings['df'] ?? 0,
            'dc_oi' => $dcSettings['oi'] ?? 0,
            'dc_di' => $dcSettings['di'] ?? 0,
            'dc_bh' => $dcSettings['bh'] ?? 0,
        ];
    }

    /**
     * Safely convert mixed value to string
     */
    private function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '';
    }

    /**
     * Build all player snapshots from roster data and POST data
     *
     * @param list<array<string, mixed>> $rosterPlayers
     * @param array<string, mixed> $postData
     * @return list<PlayerSnapshotData>
     */
    private function buildAllSnapshots(array $rosterPlayers, array $postData): array
    {
        $snapshots = [];
        $ordinal = 1;

        foreach ($rosterPlayers as $player) {
            $dcSettings = $this->slotResolver->resolveSlotSettings($player, $postData, $ordinal);
            if ($dcSettings === null) {
                $ordinal++;
                continue;
            }

            $snapshots[] = $this->buildPlayerSnapshot($player, $dcSettings, $ordinal);
            $ordinal++;
        }

        return $snapshots;
    }

    private function calculateNextSimStartDate(string $lastSimEndDate): string
    {
        $date = new \DateTime($lastSimEndDate);
        $date->modify('+1 day');
        return $date->format('Y-m-d');
    }

    /**
     * Memoized active-DC fetch, keyed by teamid.
     *
     * @return SavedDepthChartRow|null
     */
    private function getActiveDc(int $teamid): ?array
    {
        if (!array_key_exists($teamid, $this->activeDcCache)) {
            $this->activeDcCache[$teamid] = $this->repository->getActiveDepthChartForTeam($teamid);
        }

        return $this->activeDcCache[$teamid];
    }

    /**
     * @see DepthChartSnapshotServiceInterface::nameOrCreateActive()
     * @return array{success: bool, id: int, name: string}|array{success: bool, error: string}
     */
    public function nameOrCreateActive(int $teamid, string $username, string $name, Season $season): array
    {
        $activeDc = $this->getActiveDc($teamid);

        if ($activeDc !== null) {
            $this->repository->updateName($activeDc['id'], $teamid, $name);
            $this->repository->deactivateOthersForTeam($teamid, $activeDc['id'], $season->lastSimEndDate, $season->lastSimNumber);
            unset($this->activeDcCache[$teamid]);
            return ['success' => true, 'id' => $activeDc['id'], 'name' => $name];
        }

        // No active DC — create one from live ibl_plr values
        $livePlayers = $this->repository->getLiveRosterSettings($teamid);
        if ($livePlayers === []) {
            return ['success' => false, 'error' => 'No players found on roster'];
        }

        $snapshots = [];
        foreach ($livePlayers as $player) {
            $snapshots[] = [
                'pid' => $player['pid'],
                'player_name' => $player['name'],
                'ordinal' => $player['ordinal'],
                'dc_pg_depth' => $player['dc_pg_depth'],
                'dc_sg_depth' => $player['dc_sg_depth'],
                'dc_sf_depth' => $player['dc_sf_depth'],
                'dc_pf_depth' => $player['dc_pf_depth'],
                'dc_c_depth' => $player['dc_c_depth'],
                'dc_can_play_in_game' => $player['dc_can_play_in_game'],
                'dc_minutes' => $player['dc_minutes'],
                'dc_of' => $player['dc_of'],
                'dc_df' => $player['dc_df'],
                'dc_oi' => $player['dc_oi'],
                'dc_di' => $player['dc_di'],
                'dc_bh' => $player['dc_bh'],
            ];
        }

        $simStartDate = $this->calculateNextSimStartDate($season->lastSimEndDate);
        $simNumberStart = $season->lastSimNumber + 1;

        $dcId = $this->repository->createSavedDepthChart(
            $teamid,
            $username,
            $name,
            $season->phase,
            $season->endingYear,
            $simStartDate,
            $simNumberStart
        );

        $this->repository->saveDepthChartPlayers($dcId, $snapshots);

        return ['success' => true, 'id' => $dcId, 'name' => $name];
    }

    /**
     * Check if saved depth chart settings match live ibl_plr settings exactly
     *
     * Returns true only if the same set of PIDs exist and all 12 dc_* columns
     * match for every player. Detects roster changes from trades.
     *
     * @param list<SavedDepthChartPlayerRow> $dcPlayers Saved DC player settings
     * @param list<array{pid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int, ...<string, mixed>}> $liveRosterPlayers Live ibl_plr settings
     */
    private function isDepthChartMatchingLive(array $dcPlayers, array $liveRosterPlayers): bool
    {
        // Build map of live settings by PID
        /** @var array<int, array{pid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> $liveByPid */
        $liveByPid = [];
        foreach ($liveRosterPlayers as $player) {
            $liveByPid[$player['pid']] = $player;
        }

        // Build map of saved settings by PID
        /** @var array<int, SavedDepthChartPlayerRow> $savedByPid */
        $savedByPid = [];
        foreach ($dcPlayers as $player) {
            $savedByPid[$player['pid']] = $player;
        }

        // PIDs must match exactly (detect trades)
        $livePids = array_keys($liveByPid);
        $savedPids = array_keys($savedByPid);
        sort($livePids);
        sort($savedPids);
        if ($livePids !== $savedPids) {
            return false;
        }

        // Compare all 12 dc_* columns for each player
        $dcColumns = [
            'dc_pg_depth', 'dc_sg_depth', 'dc_sf_depth', 'dc_pf_depth', 'dc_c_depth',
            'dc_can_play_in_game', 'dc_minutes', 'dc_of', 'dc_df', 'dc_oi', 'dc_di', 'dc_bh',
        ];

        foreach ($savedByPid as $pid => $savedPlayer) {
            $livePlayer = $liveByPid[$pid];

            foreach ($dcColumns as $col) {
                if ($savedPlayer[$col] !== $livePlayer[$col]) {
                    return false;
                }
            }
        }

        return true;
    }

}
