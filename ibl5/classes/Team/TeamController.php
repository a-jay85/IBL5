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
        OpenTable();

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

        // Mobile Team Functions Bar (visible only on mobile/tablet)
        if ($teamID > 0) {
            echo $this->renderMobileTeamActions($teamID, $team);
        }

        echo "<table>
            <tr>
                <td align=center valign=top>";

        \UI::displaytopmenu($this->db, $teamID);

        // Team logo with responsive sizing
        echo "<div class=\"team-logo-container\"><img src=\"./{$imagesPath}logo/$teamID.jpg\" class=\"team-logo\" alt=\"" . htmlspecialchars($team->name ?? 'Team', ENT_QUOTES) . " logo\"></div>";
                
        if ($yr != "") {
            echo "<center><h1>$yr $team->name</h1></center>";
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
        <table align=center>
            <tr>
                <td align=center><table><tr>$tabs</tr></table></td>
            </tr>
            <tr>
                <td align=center>$table_output</td>
            </tr>
            <tr>
                <td align=center>$starters_table</td>
            </tr>
            <tr bgcolor=$team->color1>
                <td><font color=$team->color2><b><center>Draft Picks</center></b></font></td>
            </tr>
            <tr>
                <td>$tableDraftPicks</td>
            </tr>
            <tr>
                <td>$rafters</td>
            </tr>
        </table>";

        echo "</td><td valign=top>$team_info_right</td></tr></table>";

        CloseTable();
        \Nuke\Footer::footer();
    }

    /**
     * Display main menu
     */
    public function displayMenu(): void
    {
        \Nuke\Header::header();
        OpenTable();

        \UI::displaytopmenu($this->db, 0);

        CloseTable();
        \Nuke\Footer::footer();
    }

    /**
     * Render mobile-friendly team actions bar
     * Shows key team management functions as horizontal scrollable buttons on mobile
     */
    private function renderMobileTeamActions(int $teamID, $team): string
    {
        $teamColor = $team->color1 ?? '1E3A5F';

        $actions = [
            ['label' => 'Depth Chart', 'url' => "modules.php?name=Depth_Chart&teamID=$teamID", 'icon' => '📋'],
            ['label' => 'Schedule', 'url' => "modules.php?name=Schedule&teamID=$teamID", 'icon' => '📅'],
            ['label' => 'Trade', 'url' => "modules.php?name=Trading&op=offer&teamID=$teamID", 'icon' => '🔄'],
            ['label' => 'Waivers', 'url' => "modules.php?name=Team&op=team&teamID=0", 'icon' => '📝'],
        ];

        $html = '<div class="team-actions-mobile">';
        $html .= '<div class="team-actions-scroll">';

        foreach ($actions as $action) {
            $html .= '<a href="' . htmlspecialchars($action['url'], ENT_QUOTES) . '" class="team-action-btn">';
            $html .= '<span class="team-action-icon">' . $action['icon'] . '</span>';
            $html .= '<span class="team-action-label">' . htmlspecialchars($action['label'], ENT_QUOTES) . '</span>';
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
