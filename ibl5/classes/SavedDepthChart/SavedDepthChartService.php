<?php

declare(strict_types=1);

namespace SavedDepthChart;

use SavedDepthChart\Contracts\SavedDepthChartServiceInterface;
use SavedDepthChart\Contracts\SavedDepthChartRepositoryInterface;
use Season\Season;

/**
 * @phpstan-import-type SavedDepthChartRow from Contracts\SavedDepthChartRepositoryInterface
 * @phpstan-import-type SavedDepthChartPlayerRow from Contracts\SavedDepthChartRepositoryInterface
 * @phpstan-import-type PlayerSnapshotData from Contracts\SavedDepthChartRepositoryInterface
 *
 * @see SavedDepthChartServiceInterface
 */
class SavedDepthChartService implements SavedDepthChartServiceInterface
{
    private SavedDepthChartRepository $repository;
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->repository = new SavedDepthChartRepository($db);
    }

    /**
     * @see SavedDepthChartServiceInterface::saveOnSubmit()
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

                    \Logging\LoggerFactory::getChannel('audit')->info('depth_chart_saved', [
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

                \Logging\LoggerFactory::getChannel('audit')->info('depth_chart_saved', [
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

            \Logging\LoggerFactory::getChannel('audit')->info('depth_chart_saved', [
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
     * @see SavedDepthChartServiceInterface::loadSavedDepthChart()
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
     * @see SavedDepthChartServiceInterface::getWinLossRecord()
     * @return array{wins: int, losses: int}
     */
    public function getWinLossRecord(int $teamid, string $startDate, string $endDate): array
    {
        $query = "SELECT
            SUM(CASE WHEN (visitor_teamid = ? AND visitor_score > home_score) OR (home_teamid = ? AND home_score > visitor_score) THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN (visitor_teamid = ? AND visitor_score < home_score) OR (home_teamid = ? AND home_score < visitor_score) THEN 1 ELSE 0 END) as losses
            FROM ibl_schedule
            WHERE game_date BETWEEN ? AND ?
              AND (visitor_teamid = ? OR home_teamid = ?)
              AND (visitor_score > 0 OR home_score > 0)";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            return ['wins' => 0, 'losses' => 0];
        }

        $stmt->bind_param(
            'iiiissii',
            $teamid, $teamid, $teamid, $teamid,
            $startDate, $endDate,
            $teamid, $teamid
        );
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return ['wins' => 0, 'losses' => 0];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        if (!is_array($row)) {
            return ['wins' => 0, 'losses' => 0];
        }

        return [
            'wins' => (int) ($row['wins'] ?? 0),
            'losses' => (int) ($row['losses'] ?? 0),
        ];
    }

    /**
     * Build label for the "Current (Live)" dropdown entry
     *
     * Shows phase, phase-specific sim range, date range, and win-loss record.
     */
    public function buildCurrentLiveLabel(int $teamid, Season $season): string
    {
        $currentPhaseSim = $season->getPhaseSpecificSimNumber();

        $parts = [];

        // Find active DC (most recently updated) for sim range, date range, and win-loss record
        $activeDc = $this->repository->getActiveDepthChartForTeam($teamid);

        // Use the active DC's name if it has one, otherwise default label
        if ($activeDc !== null && $activeDc['name'] !== null && $activeDc['name'] !== '') {
            $parts[] = $activeDc['name'] . ' (Live)';
        } else {
            $parts[] = 'Current (Live)';
        }

        // Sim range: active DC's start through current sim
        if ($activeDc !== null) {
            $phaseSimStart = $season->calculatePhaseSimNumber(
                $activeDc['sim_number_start'],
                $activeDc['phase'],
                $activeDc['season_year']
            );
            $parts[] = $this->formatSimRange($season->phase, $phaseSimStart, $currentPhaseSim);

            // Date range: active DC start through current sim end (or projected if no sim has consumed this DC yet)
            $rawStartDate = $activeDc['sim_start_date'];
            $rawEndDate = $season->lastSimEndDate;
            if ($rawStartDate > $rawEndDate) {
                $rawEndDate = $season->projectedNextSimEndDate->format('Y-m-d');
            }

            $startDate = (new \DateTime($rawStartDate))->format('M j');
            $endDate = (new \DateTime($rawEndDate))->format('M j');
        } else {
            $parts[] = 'Sim ' . $currentPhaseSim;
            $startDate = (new \DateTime($season->lastSimStartDate))->format('M j');
            $endDate = (new \DateTime($season->lastSimEndDate))->format('M j');
        }

        $parts[] = $startDate . ' - ' . $endDate;

        // Win-loss record for the active DC's span
        if ($activeDc !== null) {
            $record = $this->getWinLossRecord($teamid, $activeDc['sim_start_date'], $season->lastSimEndDate);
            $parts[] = '(' . $record['wins'] . '-' . $record['losses'] . ')';
        }

        return implode(' ∙ ', $parts);
    }

    /**
     * @see SavedDepthChartServiceInterface::getDropdownOptions()
     * @return list<array{id: int, label: string, isActive: bool}>
     */
    public function getDropdownOptions(int $teamid, Season $season): array
    {
        $savedDcs = $this->repository->getSavedDepthChartsForTeam($teamid);

        // Find active DC (most recently updated if multiple)
        $activeDc = $this->repository->getActiveDepthChartForTeam($teamid);

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

            $label = $this->buildDropdownLabel($dc, $season, $teamid);
            $options[] = [
                'id' => $dc['id'],
                'label' => $label,
                'isActive' => $dc['is_active'] === 1,
            ];
        }

        return $options;
    }

    /**
     * @see SavedDepthChartServiceInterface::buildPlayerSnapshot()
     * @param array<string, mixed> $rosterPlayer
     * @param array<string, int> $dcSettings
     * @return PlayerSnapshotData
     */
    public function buildPlayerSnapshot(array $rosterPlayer, array $dcSettings, int $ordinal): array
    {
        return [
            'pid' => $this->toInt($rosterPlayer['pid'] ?? 0),
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
     * Safely convert mixed value to int
     */
    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        return 0;
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
            $pidKey = $this->findPidIndex($player, $postData, $ordinal);
            if ($pidKey === 0) {
                $ordinal++;
                continue;
            }

            $dcSettings = [
                'pg' => $this->extractIntFromPost($postData, 'pg' . $pidKey),
                'sg' => $this->extractIntFromPost($postData, 'sg' . $pidKey),
                'sf' => $this->extractIntFromPost($postData, 'sf' . $pidKey),
                'pf' => $this->extractIntFromPost($postData, 'pf' . $pidKey),
                'c' => $this->extractIntFromPost($postData, 'c' . $pidKey),
                'canPlayInGame' => $this->extractIntFromPost($postData, 'canPlayInGame' . $pidKey),
                'min' => $this->extractIntFromPost($postData, 'min' . $pidKey),
                'of' => $this->extractIntFromPost($postData, 'OF' . $pidKey),
                'df' => $this->extractIntFromPost($postData, 'DF' . $pidKey),
                'oi' => $this->extractIntFromPost($postData, 'OI' . $pidKey),
                'di' => $this->extractIntFromPost($postData, 'DI' . $pidKey),
                'bh' => $this->extractIntFromPost($postData, 'BH' . $pidKey),
            ];

            $snapshots[] = $this->buildPlayerSnapshot($player, $dcSettings, $ordinal);
            $ordinal++;
        }

        return $snapshots;
    }

    /**
     * Find the form index for a player by matching pid fields in POST data
     *
     * Falls back to ordinal position if pid fields aren't present
     *
     * @param array<string, mixed> $player
     * @param array<string, mixed> $postData
     */
    private function findPidIndex(array $player, array $postData, int $ordinal): int
    {
        $playerPid = $this->toInt($player['pid'] ?? 0);

        // Try to match by pid hidden field
        for ($i = 1; $i <= 15; $i++) {
            $pidField = 'pid' . $i;
            if (isset($postData[$pidField]) && $this->toInt($postData[$pidField]) === $playerPid) {
                return $i;
            }
        }

        // Fall back to matching by name (existing pattern) or ordinal
        $playerName = $this->toString($player['name'] ?? '');
        for ($i = 1; $i <= 15; $i++) {
            $nameField = 'Name' . $i;
            $postName = isset($postData[$nameField]) ? $this->toString($postData[$nameField]) : '';
            if ($postName !== '' && trim(strip_tags($postName)) === $playerName) {
                return $i;
            }
        }

        // Last resort: use ordinal position
        if (isset($postData['Name' . $ordinal])) {
            return $ordinal;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function extractIntFromPost(array $postData, string $key): int
    {
        $value = $postData[$key] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    private function calculateNextSimStartDate(string $lastSimEndDate): string
    {
        $date = new \DateTime($lastSimEndDate);
        $date->modify('+1 day');
        return $date->format('Y-m-d');
    }

    /**
     * Build a human-readable dropdown label for a saved depth chart
     *
     * @param SavedDepthChartRow $dc
     */
    private function buildDropdownLabel(array $dc, Season $season, int $teamid): string
    {
        $parts = [];

        if ($dc['name'] !== null && $dc['name'] !== '') {
            $parts[] = $dc['name'];
        }

        // Phase-specific sim number range
        $phaseSimStart = $season->calculatePhaseSimNumber(
            $dc['sim_number_start'],
            $dc['phase'],
            $dc['season_year']
        );

        if ($dc['sim_number_end'] !== null) {
            $phaseSimEnd = $season->calculatePhaseSimNumber(
                $dc['sim_number_end'],
                $dc['phase'],
                $dc['season_year']
            );
            $parts[] = $this->formatSimRange($dc['phase'], $phaseSimStart, $phaseSimEnd);
        } else {
            $parts[] = 'Sim ' . $phaseSimStart;
        }

        // Date range
        $startDate = (new \DateTime($dc['sim_start_date']))->format('M j');
        $endDateStr = $dc['sim_end_date'] !== null
            ? (new \DateTime($dc['sim_end_date']))->format('M j')
            : '?';
        $parts[] = $startDate . ' - ' . $endDateStr;

        // Win-loss record using sim_end_date or lastSimEndDate for active DCs
        $recordEndDate = $dc['sim_end_date'] ?? $season->lastSimEndDate;
        $record = $this->getWinLossRecord($teamid, $dc['sim_start_date'], $recordEndDate);
        $parts[] = '(' . $record['wins'] . '-' . $record['losses'] . ')';

        return implode(' | ', $parts);
    }

    /**
     * Format a sim number range with proper pluralization
     *
     * Returns "{phase} Sim {n}" for single sim, "{phase} Sims {start}-{end}" for range.
     */
    private function formatSimRange(string $phase, int $start, int $end): string
    {
        if ($start === $end) {
            return 'Sim ' . $start;
        }

        return 'Sims ' . $start . '-' . $end;
    }

    /**
     * @see SavedDepthChartServiceInterface::nameOrCreateActive()
     * @return array{success: bool, id: int, name: string}|array{success: bool, error: string}
     */
    public function nameOrCreateActive(int $teamid, string $username, string $name, Season $season): array
    {
        $activeDc = $this->repository->getActiveDepthChartForTeam($teamid);

        if ($activeDc !== null) {
            $this->repository->updateName($activeDc['id'], $teamid, $name);
            $this->repository->deactivateOthersForTeam($teamid, $activeDc['id'], $season->lastSimEndDate, $season->lastSimNumber);
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
     * @param list<array{pid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> $liveRosterPlayers Live ibl_plr settings
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
