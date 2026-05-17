<?php

declare(strict_types=1);

namespace DepthChartEntry;

use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * HTMX endpoint handler for depth chart entry tab switching
 *
 * Returns the table HTML for a given display mode without the full page layout.
 * Emits HX-Push-Url header so HTMX pushes the user-friendly URL.
 */
class DepthChartEntryApiHandler
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
    private TeamIdentityRepositoryInterface $commonRepo;
    private \League\LeagueContext $leagueContext;

    public function __construct(\mysqli $db, TeamIdentityRepositoryInterface $commonRepo, \League\LeagueContext $leagueContext)
    {
        $this->db = $db;
        $this->commonRepo = $commonRepo;
        $this->leagueContext = $leagueContext;
    }

    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $teamid = isset($_GET['teamid']) && is_string($_GET['teamid']) ? (int) $_GET['teamid'] : 0;

        $display = 'ratings';
        if (isset($_GET['display']) && is_string($_GET['display'])) {
            $rawDisplay = $_GET['display'];
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
        }

        // Validate split parameter when display=split
        $split = null;
        if ($display === 'split' && isset($_GET['split']) && is_string($_GET['split'])) {
            $splitRepo = new \Team\SplitStatsRepository($this->db);
            $rawSplit = $_GET['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            $display = 'ratings';
        }

        // Emit HX-Push-Url so HTMX pushes the user-friendly URL
        $pushUrl = 'modules.php?name=DepthChartEntry&display=' . $display;
        if ($split !== null) {
            $pushUrl .= '&split=' . $split;
        }
        header('HX-Push-Url: ' . $pushUrl);

        $controller = new DepthChartEntryController($this->db, $this->commonRepo, $this->leagueContext);
        echo $controller->getTableOutput($teamid, $display, $split);
    }
}
