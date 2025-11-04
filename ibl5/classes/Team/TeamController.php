<?php

namespace Team;

use Player\Player;

/**
 * TeamController - Main controller for Team module
 * 
 * Coordinates between Repository, Services, and UI components
 * following the MVC pattern used in other refactored modules.
 */
class TeamController
{
    private $db;
    private $repository;
    private $statsService;
    private $uiService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repository = new TeamRepository($db);
        $this->statsService = new TeamStatsService($db);
        $this->uiService = new TeamUIService($db, $this->repository);
    }

    /**
     * Display team page with roster and stats
     */
    public function displayTeamPage(int $teamID): void
    {
        $teamID = (int) $teamID;
        
        if ($teamID > 0) {
            $team = \Team::initialize($this->db, $teamID);
        }
        
        $sharedFunctions = new \Shared($this->db);
        $season = new \Season($this->db);

        $yr = $_REQUEST['yr'] ?? null;
        $display = $_REQUEST['display'] ?? 'ratings';

        \Nuke\Header::header();
        OpenTable();

        $isFreeAgencyModuleActive = $sharedFunctions->isFreeAgencyModuleActive();

        // Get the appropriate roster based on team and conditions
        if ($teamID == 0) { // Free Agents
            if ($isFreeAgencyModuleActive == 0) {
                $result = $this->repository->getFreeAgents(false);
            } else {
                $result = $this->repository->getFreeAgents(true);
            }
        } else if ($teamID == "-1") { // Entire League
            $result = $this->repository->getEntireLeagueRoster();
        } else { // Specific team
            if ($yr != "") {
                $result = $this->repository->getHistoricalRoster($teamID, $yr);
            } else if ($isFreeAgencyModuleActive == 1) {
                $result = $this->repository->getFreeAgencyRoster($teamID);
            } else {
                $result = $this->repository->getRosterUnderContract($teamID);
            }
        }

        echo "<table>
            <tr>
                <td align=center valign=top>";
                
        \UI::displaytopmenu($this->db, $team->teamID);
                
        echo "<img src=\"./images/logo/$teamID.jpg\">";
                
        if ($yr != "") {
            echo "<center><h1>$yr $team->name</h1></center>";
            $insertyear = "&yr=$yr";
        } else {
            $insertyear = "";
        }

        // Build tabs
        $tabs = $this->uiService->renderTabs($teamID, $display, $insertyear, $team);
        $tabs .= $this->uiService->addPlayoffTab($display, $teamID, $insertyear, $season);
        $tabs .= $this->uiService->addContractsTab($display, $teamID, $insertyear);

        // Get display content
        $showing = $this->uiService->getDisplayTitle($display);
        $table_output = $this->uiService->getTableOutput($display, $this->db, $result, $team, $yr, $season, $sharedFunctions);

        // Get starters table if applicable
        $starters_table = "";
        if ($teamID > 0 AND $yr == "") {
            $starters_table = $this->statsService->getLastSimsStarters($result, $team);
        }

        // Get draft picks
        $tableDraftPicks = $team ? \UI\Modules\Team::draftPicks($this->db, $team) : "";

        // Get team info right sidebar
        $inforight = $this->uiService->renderTeamInfoRight($team);
        $team_info_right = $inforight[0];
        $rafters = $inforight[1];

        echo "
        <table align=center>
            <tr bgcolor=$team->color1>
                <td><font color=$team->color2><b><center>$showing (Sortable by clicking on Column Heading)</center></b></font></td>
            </tr>
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
     * Display injuries page
     */
    public function displayInjuries(int $teamID): void
    {
        $teamID = (int) $teamID;
        $league = new \League($this->db);

        \Nuke\Header::header();
        OpenTable();

        \UI::displaytopmenu($this->db, $teamID);

        echo "<center><h2>INJURED PLAYERS</h2></center>
            <table>
                <tr>
                    <td valign=top>
                        <table class=\"sortable\">
                            <tr>
                                <th>Pos</th>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Days Injured</th>
                            </tr>";

        $i = 0;
        foreach ($league->getInjuredPlayersResult() as $injuredPlayer) {
            $player = Player::withPlrRow($this->db, $injuredPlayer);
            $team = \Team::initialize($this->db, $player->teamID);

            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "DDDDDD";

            echo "<tr bgcolor=$bgcolor>
                <td>$player->position</td>
                <td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>
                <td bgcolor=\"#$team->color1\">
                    <font color=\"#$team->color2\"><a href=\"./modules.php?name=Team&op=team&teamID=$player->teamID\">$team->city $player->teamName</a></font>
                </td>
                <td>$player->daysRemainingForInjury</td>
            </tr>";

            $i++;
        }

        echo "</table></table>";

        CloseTable();
        \Nuke\Footer::footer();
    }

    /**
     * Display draft history page
     */
    public function displayDraftHistory(int $teamID): void
    {
        $teamID = (int) $teamID;

        \Nuke\Header::header();
        OpenTable();
        \UI::displaytopmenu($this->db, $teamID);

        $team = \Team::initialize($this->db, $teamID);

        echo "$team->name Draft History
            <table class=\"sortable\">
                <tr>
                    <th>Player</th>
                    <th>Pos</th>
                    <th>Year</th>
                    <th>Round</th>
                    <th>Pick</th>
                </tr>";

        foreach ($team->getDraftHistoryResult() as $playerRow) {
            $player = Player::withPlrRow($this->db, $playerRow);

            echo "<tr>";

            if ($player->isRetired) {
                echo "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a> (retired)</td>";
            } else {
                echo "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>";
            }

            echo "
                <td>$player->position</td>
                <td>$player->draftYear</td>
                <td>$player->draftRound</td>
                <td>$player->draftPickNumber</td>
            </tr>";
        }

        echo "</table>";

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
}
