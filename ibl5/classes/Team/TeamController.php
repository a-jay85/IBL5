<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\TeamControllerInterface;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamViewInterface;

/**
 * @see TeamControllerInterface
 */
class TeamController implements TeamControllerInterface
{
    private object $db;
    private TeamServiceInterface $service;
    private TeamViewInterface $view;

    public function __construct(object $db)
    {
        $this->db = $db;
        $repository = new TeamRepository($db);
        $this->service = new TeamService($db, $repository);
        $this->view = new TeamView();
    }

    /**
     * @see TeamControllerInterface::displayTeamPage()
     */
    public function displayTeamPage(int $teamID): void
    {
        $yr = $_REQUEST['yr'] ?? null;
        $display = $_REQUEST['display'] ?? 'ratings';

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
