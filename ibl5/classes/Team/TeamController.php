<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamControllerInterface;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamViewInterface;

/**
 * @phpstan-import-type TeamPageData from Contracts\TeamServiceInterface
 *
 * @see TeamControllerInterface
 */
class TeamController implements TeamControllerInterface
{
    private \mysqli $db;
    private TeamServiceInterface $service;
    private TeamViewInterface $view;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $repository = new TeamRepository($db);
        $this->service = new TeamService($db, $repository);
        $this->view = new TeamView();
    }

    /**
     * Valid display modes for team page
     */
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

    /**
     * @see TeamControllerInterface::displayTeamPage()
     */
    public function displayTeamPage(int $teamID): void
    {
        // Validate and sanitize year parameter
        $yr = null;
        if (isset($_REQUEST['yr']) && is_string($_REQUEST['yr']) && $_REQUEST['yr'] !== '') {
            // Year should be a 4-digit year or a season range like "2024-25"
            $rawYr = $_REQUEST['yr'];
            if (preg_match('/^\d{4}(-\d{2})?$/', $rawYr) === 1) {
                $yr = $rawYr;
            }
            // Invalid year format is silently ignored (falls back to current season)
        }

        // Validate display parameter against whitelist
        $display = 'ratings';
        if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])) {
            $rawDisplay = $_REQUEST['display'];
            if (in_array($rawDisplay, self::VALID_DISPLAY_MODES, true)) {
                $display = $rawDisplay;
            }
            // Invalid display value is silently ignored (falls back to 'ratings')
        }

        // Validate split parameter when display=split
        $split = null;
        if ($display === 'split' && isset($_REQUEST['split']) && is_string($_REQUEST['split'])) {
            $splitRepo = new SplitStatsRepository($this->db);
            $rawSplit = $_REQUEST['split'];
            if (in_array($rawSplit, $splitRepo->getValidSplitKeys(), true)) {
                $split = $rawSplit;
            } else {
                // Invalid split key falls back to ratings
                $display = 'ratings';
            }
        } elseif ($display === 'split') {
            // display=split without a split key falls back to ratings
            $display = 'ratings';
        }

        \PageLayout\PageLayout::header();

        $userTeamName = '';
        global $user;
        if (is_user($user)) {
            $userInfo = getusrinfo($user);
            if (is_array($userInfo)) {
                $rawTeam = $userInfo['user_ibl_team'] ?? '';
                if (is_string($rawTeam) && $rawTeam !== '') {
                    $userTeamName = $rawTeam;
                }
            }
        }

        try {
            $pageData = $this->service->getTeamPageData($teamID, $yr, $display, $userTeamName, $split);
        } catch (\RuntimeException $e) {
            echo '<div class="ibl-alert ibl-alert--error">Team not found.</div>';
            \PageLayout\PageLayout::footer();
        }

        echo $this->view->render($pageData);

        // Output JS configuration for AJAX tab/dropdown switching
        $params = ['teamID' => $teamID];
        if ($yr !== null) {
            $params['yr'] = $yr;
        }
        $jsConfig = json_encode([
            'apiBaseUrl' => 'modules.php?name=Team&op=api',
            'params' => $params,
            'fallbackBaseUrl' => 'modules.php?name=Team&op=team&teamID=' . $teamID,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_AJAX_TABS_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/ajax-tabs.js" defer></script>';

        \PageLayout\PageLayout::footer();
    }

    /**
     * Display main menu
     */
    public function displayMenu(): void
    {
        \PageLayout\PageLayout::header();
        \PageLayout\PageLayout::footer();
    }
}
