<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/
/*         Additional security & Abstraction layer conversion           */
/*                           2003 chatserv                              */
/*      http://www.nukefixes.com -- http://www.nukeresources.com        */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function team($tid)
{
    global $db;
    $tid = intval($tid);
    if ($tid > 0) {
        $team = Team::withTeamID($db, $tid);
    }
    $sharedFunctions = new Shared($db);
    $season = new Season($db);

    $yr = $_REQUEST['yr'];

    $display = $_REQUEST['display'];
    if ($display == null) {
        $display = "ratings";
    }

    Nuke\Header::header();
    OpenTable();

    //=============================
    //DISPLAY TOP MENU
    //=============================

    UI::displaytopmenu($db, $team->teamID);

    //=============================
    //GET CONTRACT AMOUNTS CORRECT
    //=============================

    $isFreeAgencyModuleActive = $sharedFunctions->isFreeAgencyModuleActive();

    if ($tid == 0) { // Team 0 is the Free Agents; we want a query that will pick up all of their players.
        if ($isFreeAgencyModuleActive == 0) {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC";
        } else {
            $query = "SELECT * FROM ibl_plr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
        }
        $result = $db->sql_query($query);
    } else if ($tid == "-1") { // SHOW ENTIRE LEAGUE
        $query = "SELECT * FROM ibl_plr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC";
        $result = $db->sql_query($query);
    } else { // If not Free Agents, use the code below instead.
        if ($yr != "") {
            $query = "SELECT * FROM ibl_hist WHERE teamid = '$tid' AND year = '$yr' ORDER BY name ASC";
            $result = $db->sql_query($query);
        } else if ($isFreeAgencyModuleActive == 1) {
            $result = $team->getFreeAgencyRosterOrderedByNameResult();
        } else {
            $result = $team->getRosterUnderContractOrderedByNameResult();
        }
    }

    echo "<table><tr><td align=center valign=top><img src=\"./images/logo/$tid.jpg\">";

    if ($yr != "") {
        echo "<center><h1>$yr $team->name</h1></center>";
        $insertyear = "&yr=$yr";
    } else {
        $insertyear = "";
    }

    $tabs = "";
    if ($display == "ratings") {
        $showing = "Player Ratings";
        $table_ratings = UI::ratings($db, $result, $team, $yr, $season);
        $table_output = $table_ratings;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a></td>";

    if ($display == "total_s") {
        $showing = "Season Totals";
        $table_totals = UI::seasonTotals($db, $result, $team, $yr);
        $table_output = $table_totals;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a></td>";

    if ($display == "avg_s") {
        $showing = "Season Averages";
        $table_averages = UI::seasonAverages($db, $result, $team, $yr);
        $table_output = $table_averages;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a></td>";

    if ($display == "per36mins") {
        $showing = "Per 36 Minutes";
        $table_per36Minutes = UI::per36Minutes($db, $result, $team, $yr);
        $table_output = $table_per36Minutes;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=per36mins$insertyear\">Per 36 Minutes</a></td>";

    if ($display == "chunk") {
        $showing = "Chunk Averages";
        $table_periodAverages = UI::periodAverages($db, $team, $season);
        $table_output = $table_periodAverages;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a></td>";

    if (
        $season->phase == "Playoffs"
        OR $season->phase == "Draft"
        OR $season->phase == "Free Agency"
    ) {
        $playoffsStartDate = $season->endingYear . "-" . Season::IBL_PLAYOFF_MONTH . "-01";
        $playoffsEndDate = $season->endingYear . "-" . Season::IBL_PLAYOFF_MONTH . "-30";
        if ($display == "playoffs") {
            $showing = "Playoff Averages";
            $table_periodAverages = UI::periodAverages($db, $team, $season, $playoffsStartDate, $playoffsEndDate);
            $table_output = $table_periodAverages;
            $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
        } else {
            $tabs .= "<td>";
        }
        $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=playoffs$insertyear\">Playoffs Averages</a></td>";
    }

    if ($display == "contracts") {
        $showing = "Contracts";
        $table_contracts = UI::contracts($db, $result, $team, $sharedFunctions);
        $table_output = $table_contracts;
        $tabs .= "<td bgcolor=#BBBBBB style=\"font-weight:bold\">";
    } else {
        $tabs .= "<td>";
    }
    $tabs .= "<a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";

    if ($tid != 0 AND $yr == "") {
        $starters_table = lastSimsStarters($db, $result, $team->color1, $team->color2);
    }

    $table_draftpicks = draftPicks($db, $team->name);

    $inforight = team_info_right($team);
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
            <td>$table_draftpicks</td>
        </tr>
		<tr>
            <td>$rafters</td>
        </tr>
    </table>";

    // TRANSITIONS TO NEXT SIDE OF PAGE
    echo "</td><td valign=top>$team_info_right</td></tr></table>";

    CloseTable();
    Nuke\Footer::footer();
}

function lastSimsStarters($db, $result, $color1, $color2)
{
    $num = $db->sql_numrows($result);
    $i = 0;
    while ($i < $num) {
        if ($db->sql_result($result, $i, "PGDepth") == 1) {
            $startingPG = $db->sql_result($result, $i, "name");
            $startingPGpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "SGDepth") == 1) {
            $startingSG = $db->sql_result($result, $i, "name");
            $startingSGpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "SFDepth") == 1) {
            $startingSF = $db->sql_result($result, $i, "name");
            $startingSFpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "PFDepth") == 1) {
            $startingPF = $db->sql_result($result, $i, "name");
            $startingPFpid = $db->sql_result($result, $i, "pid");
        }
        if ($db->sql_result($result, $i, "CDepth") == 1) {
            $startingC = $db->sql_result($result, $i, "name");
            $startingCpid = $db->sql_result($result, $i, "pid");
        }
        $i++;
    }

    $starters_table = "<table align=\"center\" border=1 cellpadding=1 cellspacing=1>
        <tr bgcolor=$color1>
            <td colspan=5><font color=$color2><center><b>Last Sim's Starters</b></center></font></td>
        </tr>
        <tr>
            <td><center><b>PG</b><br><img src=\"./images/player/$startingPGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPGpid\">$startingPG</a></td>
            <td><center><b>SG</b><br><img src=\"./images/player/$startingSGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSGpid\">$startingSG</a></td>
            <td><center><b>SF</b><br><img src=\"./images/player/$startingSFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSFpid\">$startingSF</a></td>
            <td><center><b>PF</b><br><img src=\"./images/player/$startingPFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPFpid\">$startingPF</a></td>
            <td><center><b>C</b><br><img src=\"./images/player/$startingCpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingCpid\">$startingC</a></td>
        </tr>
    </table>";

    return $starters_table;
}

function draftPicks($db, $team_name)
{
    $table_draftpicks = "<table align=\"center\">";

    $querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
    $resultpicks = $db->sql_query($querypicks);
    $numpicks = $db->sql_numrows($resultpicks);

    $query_all_team_colors = "SELECT * FROM ibl_team_info ORDER BY teamid ASC";
    $colors = $db->sql_query($query_all_team_colors);
    $num_all_team_colors = $db->sql_numrows($colors);

    $i = 0;
    while ($i < $num_all_team_colors) {
        $color_array[$i]['team_id'] = $db->sql_result($colors, $i, "teamid");
        $color_array[$i]['team_city'] = $db->sql_result($colors, $i, "team_city");
        $color_array[$i]['team_name'] = $db->sql_result($colors, $i, "team_name");
        $i++;
    }

    $hh = 0;
    while ($hh < $numpicks) {
        $teampick = $db->sql_result($resultpicks, $hh, "teampick");
        $year = $db->sql_result($resultpicks, $hh, "year");
        $round = $db->sql_result($resultpicks, $hh, "round");
        $notes = $db->sql_result($resultpicks, $hh, "notes");

        $j = 0;
        while ($j < $i) {
            $pick_team_name = $color_array[$j]['team_name'];
            if ($pick_team_name == $teampick) {
                $pick_team_id = $color_array[$j]['team_id'];
                $pick_team_city = $color_array[$j]['team_city'];
            }
            $j++;
        }
        $table_draftpicks .= "<tr>
            <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\"><img src=\"images/logo/$teampick.png\" height=33 width=33></a></td>
            <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\">$year $pick_team_city $teampick (Round $round)</a></td>
        </tr>";
        if ($notes != NULL) {
            $table_draftpicks .= "<tr>
                <td width=200 colspan=2 valign=\"top\"><i>$notes</i><br>&nbsp;</td>
            </tr>";
        }

        $hh++;
    }

    $table_draftpicks .= "</table>";

    return $table_draftpicks;
}

function team_info_right($team)
{
    global $db;

    $output = "<table bgcolor=#eeeeee width=220>";

    $output .= UI\Modules\Team::currentSeason($db, $team);
    $output .= UI\Modules\Team::gmHistory($db, $team);
    $ultimate_output[1] = UI\Modules\Team::championshipBanners($db, $team);
    $output .= UI\Modules\Team::teamAccomplishments($db, $team);
    $output .= UI\Modules\Team::resultsRegularSeason($db, $team);
    $output .= UI\Modules\Team::resultsHEAT($db, $team);
    $output .= UI\Modules\Team::resultsPlayoffs($db, $team);

    $output .= "</table>";

    $ultimate_output[0] = $output;

    return $ultimate_output;
}

function schedule(int $teamID)
{
    global $db;
    $sharedFunctions = new Shared($db);
    $season = new Season($db);
    $team = Team::withTeamID($db, $teamID);
    $wins = $losses = $winStreak = $lossStreak = 0;

    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($db, $teamID);

    echo "<center>
		<img src=\"./images/logo/$teamID.jpg\">
		<table width=600 border=1>
			<tr bgcolor=$team->color1 style=\"color:#$team->color2; text-align:center\">
                <td colspan=26>
                    <h1>Team Schedule</h1>
                    <p>
                    <i>games highlighted in yellow are projected to be run next sim (" . Sim::LENGTH_IN_DAYS . " days)</i>
                </td>
            </tr>";
    
    //TODO: unify this code with the Schedule module's chunk function

    $teamSchedule = Schedule\TeamSchedule::getSchedule($db, $team->teamID);
    
    $teamSeasonRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC;";
    $teamSeasonRecordsResult = $db->sql_query($teamSeasonRecordsQuery);
    
    $lastMonthIteratedOver = "";
    $i = 0;
    foreach ($teamSchedule as $row) {
        $game = new Game($db, $row);
        if ($game->visitorTeamID == $team->teamID) {
            $visitorTeamName = $team->name;
            $homeTeamName = $sharedFunctions->getTeamnameFromTid($game->homeTeamID);
        } else {
            $homeTeamName = $team->name;
            $visitorTeamName = $sharedFunctions->getTeamnameFromTid($game->visitorTeamID);
        }
        
        $visitorRecord = $db->sql_result($teamSeasonRecordsResult, $game->visitorTeamID - 1, "leagueRecord");
        $homeRecord = $db->sql_result($teamSeasonRecordsResult, $game->homeTeamID - 1, "leagueRecord");

        $currentMonthBeingIteratedOver = $game->dateObject->format('m');
        if ($currentMonthBeingIteratedOver != $lastMonthIteratedOver) {
            $fullMonthName = $game->dateObject->format('F');
            echo "<tr bgcolor=$team->color2 style=\"font-weight:bold; color:#$team->color1; text-align:center\">
                <td colspan=7>$fullMonthName</td>
            </tr>
            <tr bgcolor=$team->color2 style=\"font-weight:bold; color:#$team->color1\">
                <td>Date</td>
                <td>Visitor</td>
                <td>Score</td>
                <td>Home</td>
                <td>Score</td>
                <td>Record</td>
                <td>Streak</td>
            </tr>";
        }

        if ($game->visitorScore == $game->homeScore) {
            if ($game->dateObject <= $season->projectedNextSimEndDate) {
                echo "<tr bgcolor=#DDDD00>";
            } else {
                echo "<tr>";
            }
            echo "<td>$game->date</td>
                <td><a href=\"modules.php?name=Team&op=team&tid=$game->visitorTeamID\">$visitorTeamName ($visitorRecord)</a></td>
                <td></td>
                <td><a href=\"modules.php?name=Team&op=team&tid=$game->homeTeamID\">$homeTeamName ($homeRecord)</a></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>";
        } else {
            if ($teamID == $game->visitorTeamID) {
                if ($game->visitorScore > $game->homeScore) {
                    $wins++;
                    $winStreak++;
                    $lossStreak = 0;
                    $winlosscolor = "green";
                } else {
                    $losses++;
                    $lossStreak++;
                    $winStreak = 0;
                    $winlosscolor = "red";
                }
            } else {
                if ($game->visitorScore > $game->homeScore) {
                    $losses++;
                    $lossStreak++;
                    $winStreak = 0;
                    $winlosscolor = "red";
                } else {
                    $wins++;
                    $winStreak++;
                    $lossStreak = 0;
                    $winlosscolor = "green";
                }
            }

            if ($winStreak > $lossStreak) {
                $streak = "W $winStreak";
            } else {
                $streak = "L $lossStreak";
            }

            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

            if ($game->visitorScore > $game->homeScore) {
                echo "<tr bgcolor=$bgcolor>
                    <td><a href=\"./ibl/IBL/box$game->boxScoreID.htm\">$game->date</a></td>
                    <td><b><a href=\"modules.php?name=Team&op=team&tid=$game->visitorTeamID\">$visitorTeamName ($visitorRecord)</a></b></td>
                    <td><b><a href=\"./ibl/IBL/box$game->boxScoreID.htm\"><font color=$winlosscolor>$game->visitorScore</font></a></b></td>
                    <td><a href=\"modules.php?name=Team&op=team&tid=$game->homeTeamID\">$homeTeamName ($homeRecord)</a></td>
                    <td><b><a href=\"./ibl/IBL/box$game->boxScoreID.htm\"><font color=$winlosscolor>$game->homeScore</font></a></b></td>
                    <td>$wins - $losses</td>
                    <td>$streak</td>
                </tr>";
            } else if ($game->visitorScore < $game->homeScore) {
                echo "<tr bgcolor=$bgcolor>
                    <td><a href=\"./ibl/IBL/box$game->boxScoreID.htm\">$game->date</a></td>
                    <td><a href=\"modules.php?name=Team&op=team&tid=$game->visitorTeamID\">$visitorTeamName ($visitorRecord)</a></td>
                    <td><b><a href=\"./ibl/IBL/box$game->boxScoreID.htm\"><font color=$winlosscolor>$game->visitorScore</font></a></b></td>
                    <td><b><a href=\"modules.php?name=Team&op=team&tid=$game->homeTeamID\">$homeTeamName ($homeRecord)</a></b></td>
                    <td><b><a href=\"./ibl/IBL/box$game->boxScoreID.htm\"><font color=$winlosscolor>$game->homeScore</font></a></b></td>
                    <td>$wins - $losses</td>
                    <td>$streak</td>
                </tr>";
            }
        }

        $lastMonthIteratedOver = $currentMonthBeingIteratedOver;
        $i++;
    }

    echo "</center>";
    CloseTable();

    CloseTable();
    Nuke\Footer::footer();
}

function viewinjuries($tid)
{
    global $db;
    $league = new League($db);

    Nuke\Header::header();
    OpenTable();

    UI::displaytopmenu($db, $tid);

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
        $player = Player::withPlrRow($db, $injuredPlayer);
        $team = Team::withTeamID($db, $player->teamID);

        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "DDDDDD";

        echo "<tr bgcolor=$bgcolor>
            <td>$player->position</td>
            <td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>
            <td bgcolor=\"#$team->color1\">
                <font color=\"#$team->color2\"><a href=\"./modules.php?name=Team&op=team&tid=$player->teamID\">$team->city $player->teamName</a></font>
            </td>
            <td>$player->daysRemainingForInjury</td>
        </tr>";

        $i++;
    }

    echo "</table></table>";

    CloseTable();
    Nuke\Footer::footer();
}

function drafthistory($tid)
{
    global $db;

    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($db, $tid);

    $team = Team::withTeamID($db, $tid);

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
        $player = Player::withPlrRow($db, $playerRow);

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
    Nuke\Footer::footer();
}

function menu()
{
    global $db;

    Nuke\Header::header();
    OpenTable();

    UI::displaytopmenu($db, 0);

    CloseTable();
    Nuke\Footer::footer();
}

switch ($op) {
    case "team":
        team($tid);
        break;

    case "schedule":
        schedule($tid);
        break;

    case "injuries":
        viewinjuries($tid);
        break;

    case "drafthistory":
        drafthistory($tid);
        break;

    default:
        menu();
        break;
}
