<?php

declare(strict_types=1);

namespace Trading;

use Team\TeamRepository;
use Team\TeamTableService;
use UI\Components\TableViewDropdown;

/**
 * AJAX JSON endpoint handler for trade roster preview panel
 *
 * Returns a full-roster table showing what a team's roster would look like
 * post-trade, with players added/removed based on the proposed trade.
 * Reuses TeamTableService rendering and TableViewDropdown for dropdown wrapping.
 */
class TradeRosterPreviewApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'contracts',
        'split',
        'chunk',
        'playoffs',
    ];

    private const MAX_PIDS = 20;

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $teamID = $this->validateTeamID();
        if ($teamID === 0) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $addPids = $this->validatePidList('addPids');
        if ($addPids === null) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $removePids = $this->validatePidList('removePids');
        if ($removePids === null) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $display = $this->validateDisplay();

        // Validate split parameter when display is 'split'
        $split = null;
        if ($display === 'split') {
            if (isset($_GET['split']) && is_string($_GET['split'])) {
                $splitRepo = new \Team\SplitStatsRepository($this->db);
                if (in_array($_GET['split'], $splitRepo->getValidSplitKeys(), true)) {
                    $split = $_GET['split'];
                } else {
                    $display = 'ratings';
                }
            } else {
                $display = 'ratings';
            }
        }

        $bufferLevel = ob_get_level();

        try {
            $teamRepository = new TeamRepository($this->db);
            $teamTableService = new TeamTableService($this->db, $teamRepository);

            // Get the base roster and starters
            $rosterData = $teamTableService->getRosterAndStarters($teamID);
            /** @var list<array<string, mixed>> $roster */
            $roster = $rosterData['roster'];
            /** @var list<int> $starterPids */
            $starterPids = $rosterData['starterPids'];

            // Remove outgoing players
            if ($removePids !== []) {
                $roster = array_values(array_filter(
                    $roster,
                    static fn (array $row): bool => !in_array($row['pid'] ?? 0, $removePids, true)
                ));
            }

            // Fetch and append incoming players
            if ($addPids !== []) {
                $incomingPlayers = $this->fetchPlayersByPids($addPids);
                foreach ($incomingPlayers as $incoming) {
                    $roster[] = $incoming;
                }
            }

            $team = \Team::initialize($this->db, $teamID);
            $season = new \Season($this->db);

            // Build PID list for aggregate views
            /** @var list<int> $rosterPids */
            $rosterPids = [];
            foreach ($roster as $row) {
                $pid = $row['pid'] ?? null;
                if (is_int($pid) && $pid > 0) {
                    $rosterPids[] = $pid;
                }
            }

            $tableHtml = $this->renderTable($display, $roster, $team, $season, $starterPids, $rosterPids, $split, $teamTableService);

            // Wrap with dropdown
            $dropdownGroups = $teamTableService->buildDropdownGroups($season);
            $activeValue = ($display === 'split' && $split !== null) ? 'split:' . $split : $display;
            $teamData = $teamRepository->getTeam($teamID);
            $color1 = is_string($teamData['color1'] ?? null) ? $teamData['color1'] : '000000';
            $color2 = is_string($teamData['color2'] ?? null) ? $teamData['color2'] : 'FFFFFF';
            $dropdown = new TableViewDropdown($dropdownGroups, $activeValue, '', $color1, $color2);
            $wrappedHtml = $dropdown->wrap($tableHtml);

            echo json_encode(['html' => $wrappedHtml], JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            // Clean up any output buffers left open by rendering code
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
        }
    }

    /**
     * Validate teamID query parameter
     */
    private function validateTeamID(): int
    {
        if (!isset($_GET['teamID']) || !is_string($_GET['teamID'])) {
            return 0;
        }

        $raw = $_GET['teamID'];
        if (!ctype_digit($raw) || $raw === '0') {
            return 0;
        }

        return (int) $raw;
    }

    /**
     * Validate a PID list query parameter (addPids or removePids)
     *
     * Returns empty array for empty/missing values (valid — no players to add/remove).
     * Returns null for invalid values (non-numeric, exceeds max).
     *
     * @return list<int>|null Validated PID list or null if invalid
     */
    private function validatePidList(string $paramName): ?array
    {
        if (!isset($_GET[$paramName]) || !is_string($_GET[$paramName])) {
            return [];
        }

        $raw = $_GET[$paramName];
        if ($raw === '') {
            return [];
        }

        $parts = explode(',', $raw);
        if (count($parts) > self::MAX_PIDS) {
            return null;
        }

        $pids = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || !ctype_digit($trimmed)) {
                return null;
            }
            $pids[] = (int) $trimmed;
        }

        return $pids;
    }

    /**
     * Validate display query parameter against whitelist
     */
    private function validateDisplay(): string
    {
        if (!isset($_GET['display']) || !is_string($_GET['display'])) {
            return 'ratings';
        }

        $raw = $_GET['display'];
        if (in_array($raw, self::VALID_DISPLAY_MODES, true)) {
            return $raw;
        }

        return 'ratings';
    }

    /**
     * Fetch player rows by PIDs using prepared statement with IN clause
     *
     * @param list<int> $pids Player IDs
     * @return list<array<string, mixed>> Player rows from ibl_plr
     */
    private function fetchPlayersByPids(array $pids): array
    {
        if ($pids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $types = str_repeat('i', count($pids));

        $stmt = $this->db->prepare("SELECT * FROM ibl_plr WHERE pid IN ({$placeholders})");
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param($types, ...$pids);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Render the appropriate table for the given display mode
     *
     * @param list<array<string, mixed>> $roster Modified roster
     * @param list<int> $starterPids Starter PIDs from original roster
     * @param list<int> $rosterPids All PIDs in the modified roster (for aggregate queries)
     */
    private function renderTable(string $display, array $roster, \Team $team, \Season $season, array $starterPids, array $rosterPids, ?string $split, TeamTableService $teamTableService): string
    {
        switch ($display) {
            case 'chunk':
                return \UI::periodAverages($this->db, $team, $season, null, null, $starterPids, $rosterPids);
            case 'playoffs':
                return \UI::periodAverages($this->db, $team, $season, $season->playoffsStartDate, $season->playoffsEndDate, $starterPids, $rosterPids);
            case 'split':
                $splitRepo = new \Team\SplitStatsRepository($this->db);
                $splitKey = $split ?? 'home';
                $rows = $splitRepo->getSplitStats($team->teamID, $season->endingYear, $splitKey);
                $rows = array_values(array_filter($rows, static fn (array $r): bool => in_array($r['pid'], $rosterPids, true)));
                $splitLabel = $splitRepo->getSplitLabel($splitKey);
                return \UI\Tables\SplitStats::render($rows, $team, $splitLabel, $starterPids);
            default:
                return $teamTableService->renderTableForDisplay($display, $roster, $team, null, $season, $starterPids, $split);
        }
    }
}
