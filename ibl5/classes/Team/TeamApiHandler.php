<?php

declare(strict_types=1);

namespace Team;

/**
 * AJAX JSON endpoint handler for team page tab switching
 *
 * Returns the table HTML for a given display mode without the full page layout.
 */
class TeamApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'chunk',
        'playoffs',
        'contracts',
        'split',
    ];

    private \mysqli $db;
    private TeamTableService $tableService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $repository = new TeamRepository($db);
        $this->tableService = new TeamTableService($db, $repository);
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $teamID = isset($_GET['teamID']) && is_string($_GET['teamID']) ? (int) $_GET['teamID'] : 0;

        $display = 'ratings';
        if (isset($_GET['display']) && is_string($_GET['display'])) {
            $rawDisplay = $_GET['display'];
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
        }

        $yr = null;
        if (isset($_GET['yr']) && is_string($_GET['yr']) && $_GET['yr'] !== '') {
            $rawYr = $_GET['yr'];
            if (preg_match('/^\d{4}(-\d{2})?$/', $rawYr) === 1) {
                $yr = $rawYr;
            }
        }

        // Validate split parameter when display=split
        $split = null;
        if ($display === 'split' && isset($_GET['split']) && is_string($_GET['split'])) {
            $splitRepo = new SplitStatsRepository($this->db);
            $rawSplit = $_GET['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            $display = 'ratings';
        }

        $html = $this->tableService->getTableOutput($teamID, $yr, $display, $split);

        echo json_encode(['html' => $html], JSON_THROW_ON_ERROR);
    }
}
