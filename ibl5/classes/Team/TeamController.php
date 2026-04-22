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
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(\mysqli $db, ?\Utilities\NukeCompat $nukeCompat = null)
    {
        $this->db = $db;
        $repository = new TeamRepository($db);
        $this->service = new TeamService($db, $repository);
        $this->view = new TeamView();
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
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
    public function displayTeamPage(int $teamid): void
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
        if ($this->nukeCompat->isUser($user)) {
            $cookie = $this->nukeCompat->cookieDecode($user);
            $username = (string) ($cookie[1] ?? '');
            if ($username !== '') {
                $commonRepo = new \Services\CommonMysqliRepository($this->db);
                $userTeamName = $commonRepo->getTeamnameFromUsername($username) ?? '';
            }
        }

        try {
            $pageData = $this->service->getTeamPageData($teamid, $yr, $display, $userTeamName, $split);
            $pageData['extensionResult'] = isset($_GET['result']) && is_string($_GET['result']) ? $_GET['result'] : null;
            $pageData['extensionMsg'] = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : null;
        } catch (\RuntimeException $e) {
            echo '<div class="ibl-alert ibl-alert--error">Team not found.</div>';
            \PageLayout\PageLayout::footer();
            return;
        }

        echo $this->view->render($pageData);

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
