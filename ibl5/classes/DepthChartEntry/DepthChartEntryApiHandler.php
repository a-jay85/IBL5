<?php

declare(strict_types=1);

namespace DepthChartEntry;

/**
 * AJAX JSON endpoint handler for depth chart entry tab switching
 *
 * Returns the table HTML for a given display mode without the full page layout.
 */
class DepthChartEntryApiHandler
{
    private const VALID_DISPLAY_MODES = [
        'ratings',
        'total_s',
        'avg_s',
        'per36mins',
        'chunk',
        'contracts',
    ];

    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
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

        $controller = new DepthChartEntryController($this->db);
        $html = $controller->getTableOutput($teamID, $display);

        echo json_encode(['html' => $html], JSON_THROW_ON_ERROR);
    }
}
