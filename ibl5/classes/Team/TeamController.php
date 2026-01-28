<?php

declare(strict_types=1);

namespace Team;

use Player\Player;
use Team\Contracts\TeamControllerInterface;

/**
 * @see TeamControllerInterface
 */
class TeamController implements TeamControllerInterface
{
    private $db;
    private $repository;
    private $statsService;
    private $uiService;

    public function __construct(object $db)
    {
        $this->db = $db;
        $this->repository = new TeamRepository($db);
        $this->statsService = new TeamStatsService();
        $this->uiService = new TeamUIService($this->repository);
    }

    /**
     * @see TeamControllerInterface::displayTeamPage()
     */
    public function displayTeamPage(int $teamID): void
    {
        global $leagueContext;
        
        $leagueConfig = $leagueContext->getConfig();
        $imagesPath = $leagueConfig['images_path'];

        $teamID = (int) $teamID;
        
        $team = \Team::initialize($this->db, $teamID);

        $sharedFunctions = new \Shared($this->db);
        $season = new \Season($this->db);

        $yr = $_REQUEST['yr'] ?? null;
        $display = $_REQUEST['display'] ?? 'ratings';

        \Nuke\Header::header();

        $isFreeAgencyModuleActive = $sharedFunctions->isFreeAgencyModuleActive();

        if ($teamID == 0) {
            if ($isFreeAgencyModuleActive == 0) {
                $result = $this->repository->getFreeAgents(false);
            } else {
                $result = $this->repository->getFreeAgents(true);
            }
        } else if ($teamID == "-1") {
            $result = $this->repository->getEntireLeagueRoster();
        } else {
            if ($yr != "") {
                $result = $this->repository->getHistoricalRoster($teamID, $yr);
            } else if ($isFreeAgencyModuleActive == 1) {
                $result = $this->repository->getFreeAgencyRoster($teamID);
            } else {
                $result = $this->repository->getRosterUnderContract($teamID);
            }
        }

        if ($yr != "") {
            $insertyear = "&yr=$yr";
        } else {
            $insertyear = "";
        }

        $tabs = $this->uiService->renderTabs($teamID, $display, $insertyear, $season);

        $table_output = $this->uiService->getTableOutput($display, $this->db, $result, $team, $yr, $season, $sharedFunctions);

        $starters_table = "";
        if ($teamID > 0 AND $yr == "") {
            $starters_table = $this->statsService->getLastSimsStarters($result, $team);
        }

        $teamModules = new \UI\Modules\Team($this->repository);
        $tableDraftPicks = $team ? $teamModules->draftPicks($team) : "";

        $inforight = $this->uiService->renderTeamInfoRight($team);
        $team_info_right = $inforight[0];
        $rafters = $inforight[1];

        echo "
        <div class=\"team-page-layout\">
            <div class=\"team-page-main\">
                <div style=\"text-align: center; margin-bottom: 1rem;\">
                    <img src=\"./{$imagesPath}logo/$teamID.jpg\" style=\"display: block; margin: 0 auto;\">
                    " . ($yr !== "" && $yr !== null ? "<h1 class=\"ibl-title\" style=\"margin: 0.5rem 0;\">$yr $team->name</h1>" : "") . "
                    <table style=\"margin: 0 auto;\"><tr>$tabs</tr></table>
                </div>
                <div class=\"table-scroll-wrapper\">
                    <div class=\"table-scroll-container\">
                        $table_output
                    </div>
                </div>
                <div class=\"table-scroll-wrapper\">
                    <div class=\"table-scroll-container\">
                        $starters_table
                    </div>
                </div>
                <div style=\"background-color: $team->color1; text-align: center; padding: 4px;\">
                    <span style=\"color: $team->color2; font-weight: bold;\">Draft Picks</span>
                </div>
                <div class=\"table-scroll-wrapper\">
                    <div class=\"table-scroll-container\">
                        $tableDraftPicks
                    </div>
                </div>
                <div class=\"team-page-sidebar-mobile\">$team_info_right</div>
                <div class=\"team-page-rafters\">$rafters</div>
            </div>
            <div class=\"team-page-sidebar\">$team_info_right</div>
        </div>";

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
