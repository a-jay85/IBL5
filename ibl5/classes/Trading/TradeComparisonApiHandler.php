<?php

declare(strict_types=1);

namespace Trading;

use UI\Components\TableViewDropdown;

/**
 * AJAX JSON endpoint handler for trade comparison panel
 *
 * Returns stat table HTML for selected trade players, filtered by PID.
 * Reuses the same UI:: table renderers as team pages.
 */
class TradeComparisonApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'contracts',
    ];

    private const DROPDOWN_GROUPS = [
        'Views' => [
            'ratings' => 'Ratings',
            'total_s' => 'Totals',
            'avg_s' => 'Averages',
            'per36mins' => 'Per 36',
            'contracts' => 'Contracts',
        ],
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

        $pids = $this->validatePids();
        if ($pids === null) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $teamID = $this->validateTeamID();
        if ($teamID === 0) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $display = $this->validateDisplay();

        $players = $this->fetchPlayersByPids($pids);
        if ($players === []) {
            echo json_encode(['html' => ''], JSON_THROW_ON_ERROR);
            return;
        }

        $team = \Team::initialize($this->db, $teamID);
        $season = new \Season($this->db);

        $tableHtml = $this->renderTable($display, $players, $team, $season);

        $dropdown = new TableViewDropdown(
            self::DROPDOWN_GROUPS,
            $display,
            '',
            $team->color1,
            $team->color2
        );
        $wrappedHtml = $dropdown->wrap($tableHtml);

        echo json_encode(['html' => $wrappedHtml], JSON_THROW_ON_ERROR);
    }

    /**
     * Validate and parse pids query parameter
     *
     * @return list<int>|null Validated PID list or null if invalid
     */
    private function validatePids(): ?array
    {
        if (!isset($_GET['pids']) || !is_string($_GET['pids'])) {
            return null;
        }

        $raw = $_GET['pids'];
        if ($raw === '') {
            return null;
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
     * @param list<array<string, mixed>> $players Player rows
     */
    private function renderTable(string $display, array $players, \Team $team, \Season $season): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $players, $team, '');
            case 'avg_s':
                return \UI::seasonAverages($this->db, $players, $team, '');
            case 'per36mins':
                return \UI::per36Minutes($this->db, $players, $team, '');
            case 'contracts':
                return \UI::contracts($this->db, $players, $team, $season);
            default:
                return \UI::ratings($this->db, $players, $team, '', $season);
        }
    }
}
