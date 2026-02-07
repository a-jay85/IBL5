<?php

declare(strict_types=1);

namespace SavedDepthChart;

use SavedDepthChart\Contracts\SavedDepthChartServiceInterface;
use SavedDepthChart\Contracts\SavedDepthChartRepositoryInterface;

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
        int $tid,
        string $username,
        ?string $name,
        array $rosterPlayers,
        array $postData,
        int $loadedDcId,
        \Season $season
    ): int {
        $snapshots = $this->buildAllSnapshots($rosterPlayers, $postData);

        if ($loadedDcId > 0) {
            $existingDc = $this->repository->getSavedDepthChartById($loadedDcId, $tid);
            if ($existingDc !== null) {
                $this->repository->updateDepthChartPlayers($loadedDcId, $snapshots);

                if ($existingDc['is_active'] === 0) {
                    $this->repository->deactivateForTeam($tid, $season->lastSimEndDate, $season->lastSimNumber);
                    $this->repository->reactivate($loadedDcId, $tid);
                }

                return $loadedDcId;
            }
        }

        // Fresh submission: deactivate previous, create new
        $this->repository->deactivateForTeam($tid, $season->lastSimEndDate, $season->lastSimNumber);

        $simStartDate = $this->calculateNextSimStartDate($season->lastSimEndDate);
        $simNumberStart = $season->lastSimNumber + 1;

        $dcId = $this->repository->createSavedDepthChart(
            $tid,
            $username,
            $name,
            $season->phase,
            $season->endingYear,
            $simStartDate,
            $simNumberStart
        );

        $this->repository->saveDepthChartPlayers($dcId, $snapshots);

        return $dcId;
    }

    /**
     * @see SavedDepthChartServiceInterface::loadSavedDepthChart()
     * @param list<int> $currentRosterPids
     */
    public function loadSavedDepthChart(int $id, int $tid, array $currentRosterPids): ?array
    {
        $dc = $this->repository->getSavedDepthChartById($id, $tid);
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
    public function getWinLossRecord(int $tid, string $startDate, string $endDate): array
    {
        $query = "SELECT
            SUM(CASE WHEN (Visitor = ? AND VScore > HScore) OR (Home = ? AND HScore > VScore) THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN (Visitor = ? AND VScore < HScore) OR (Home = ? AND HScore < VScore) THEN 1 ELSE 0 END) as losses
            FROM ibl_schedule
            WHERE Date BETWEEN ? AND ?
              AND (Visitor = ? OR Home = ?)
              AND (VScore > 0 OR HScore > 0)";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            return ['wins' => 0, 'losses' => 0];
        }

        $stmt->bind_param(
            'iiiissii',
            $tid, $tid, $tid, $tid,
            $startDate, $endDate,
            $tid, $tid
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
     * @see SavedDepthChartServiceInterface::getDropdownOptions()
     * @return list<array{id: int, label: string, isActive: bool}>
     */
    public function getDropdownOptions(int $tid, \Season $season): array
    {
        $savedDcs = $this->repository->getSavedDepthChartsForTeam($tid);
        $options = [];

        foreach ($savedDcs as $dc) {
            $label = $this->buildDropdownLabel($dc, $season);
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
            'dc_PGDepth' => $dcSettings['pg'] ?? 0,
            'dc_SGDepth' => $dcSettings['sg'] ?? 0,
            'dc_SFDepth' => $dcSettings['sf'] ?? 0,
            'dc_PFDepth' => $dcSettings['pf'] ?? 0,
            'dc_CDepth' => $dcSettings['c'] ?? 0,
            'dc_active' => $dcSettings['active'] ?? 0,
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
                'active' => $this->extractIntFromPost($postData, 'active' . $pidKey),
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
    private function buildDropdownLabel(array $dc, \Season $season): string
    {
        $parts = [];

        if ($dc['name'] !== null && $dc['name'] !== '') {
            $parts[] = $dc['name'];
        }

        $simStart = $dc['sim_number_start'];
        $simEnd = $dc['sim_number_end'];

        if ($simEnd !== null && $simEnd > $simStart) {
            $parts[] = 'Sims ' . $simStart . '-' . $simEnd;
        } else {
            $parts[] = 'Sim ' . $simStart;
        }

        if ($dc['is_active'] === 1) {
            $parts[] = '(active)';
        }

        $startDate = (new \DateTime($dc['sim_start_date']))->format('M j');
        $endDate = $dc['sim_end_date'] !== null
            ? (new \DateTime($dc['sim_end_date']))->format('M j')
            : '?';
        $parts[] = $startDate . ' - ' . $endDate;

        return implode(' | ', $parts);
    }
}
