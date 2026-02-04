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
     * @see TeamControllerInterface::displayTeamPage()
     */
    public function displayTeamPage(int $teamID): void
    {
        $yr = null;
        if (isset($_REQUEST['yr']) && is_string($_REQUEST['yr'])) {
            $yr = $_REQUEST['yr'];
        }
        $display = 'ratings';
        if (isset($_REQUEST['display']) && is_string($_REQUEST['display'])) {
            $display = $_REQUEST['display'];
        }

        \Nuke\Header::header();

        $pageData = $this->service->getTeamPageData($teamID, $yr, $display);
        echo $this->view->render($pageData);

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
