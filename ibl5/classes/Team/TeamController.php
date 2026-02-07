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
    private TeamServiceInterface $service;
    private TeamViewInterface $view;

    public function __construct(\mysqli $db)
    {
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

        \Nuke\Header::header();

        $pageData = $this->service->getTeamPageData($teamID, $yr, $display);
        echo $this->view->render($pageData);

        // Output JS configuration for AJAX tab switching
        $jsConfig = json_encode([
            'teamId' => $teamID,
            'apiBaseUrl' => 'modules.php?name=Team&op=api',
            'yr' => $yr,
        ], JSON_THROW_ON_ERROR);
        echo '<script>window.IBL_TEAM_CONFIG = ' . $jsConfig . ';</script>';
        echo '<script src="jslib/team-tabs.js" defer></script>';

        \Nuke\Footer::footer();
    }

    /**
     * Display main menu
     */
    public function displayMenu(): void
    {
        \Nuke\Header::header();
        \Nuke\Footer::footer();
    }
}
