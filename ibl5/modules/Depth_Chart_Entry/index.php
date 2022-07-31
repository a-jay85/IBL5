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
/*                                                                      */
/* ibl College Scout Module added by Spencer Cooley                    */
/* 2/2/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$sharedFunctions = new Shared($db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

$pagetitle = " - Depth Chart Entry";

//include("modules/$module_name/navbar.php");

function userinfo($username, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db, $sharedFunctions, $useset;

    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($teamlogo);
    $queryteam = "SELECT * FROM ibl_team_info WHERE teamid = '$tid' ";
    $resultteam = $db->sql_query($queryteam);
    $color1 = $db->sql_result($resultteam, 0, "color1");
    $color2 = $db->sql_result($resultteam, 0, "color2");

    include "header.php";
    OpenTable();
    $sharedFunctions->displaytopmenu($tid);

    // === CODE TO INSERT IBL DEPTH CHART ===

    function posHandler($positionVar)
    {
        echo "<option value=\"0\"" . ($positionVar == 0 ? " SELECTED" : "") . ">No</option>";
        echo "<option value=\"1\"" . ($positionVar == 1 ? " SELECTED" : "") . ">1st</option>";
        echo "<option value=\"2\"" . ($positionVar == 2 ? " SELECTED" : "") . ">2nd</option>";
        echo "<option value=\"3\"" . ($positionVar == 3 ? " SELECTED" : "") . ">3rd</option>";
        echo "<option value=\"4\"" . ($positionVar == 4 ? " SELECTED" : "") . ">4th</option>";
        echo "<option value=\"5\"" . ($positionVar == 5 ? " SELECTED" : "") . ">ok</option>";
    }

    function offdefHandler($focusVar)
    {
        echo "<option value=\"0\"" . ($focusVar == 0 ? " SELECTED" : "") . ">Auto</option>";
        echo "<option value=\"1\"" . ($focusVar == 1 ? " SELECTED" : "") . ">Outside</option>";
        echo "<option value=\"2\"" . ($focusVar == 2 ? " SELECTED" : "") . ">Drive</option>";
        echo "<option value=\"3\"" . ($focusVar == 3 ? " SELECTED" : "") . ">Post</option>";
    }

    function oidibhHandler($settingVar)
    {
        echo "<option value=\"2\"" . ($settingVar == 2 ? " SELECTED" : "") . ">2</option>";
        echo "<option value=\"1\"" . ($settingVar == 1 ? " SELECTED" : "") . ">1</option>";
        echo "<option value=\"0\"" . ($settingVar == 0 ? " SELECTED" : "") . ">-</option>";
        echo "<option value=\"-1\"" . ($settingVar == -1 ? " SELECTED" : "") . ">-1</option>";
        echo "<option value=\"-2\"" . ($settingVar == -2 ? " SELECTED" : "") . ">-2</option>";
    }

    $sql7 = "SELECT * FROM ibl_offense_sets WHERE TeamName = '$teamlogo' ORDER BY SetNumber ASC";
    $result7 = $db->sql_query($sql7);
    $num7 = $db->sql_numrows($result7);

    $queryPlayersOnTeam = "SELECT * FROM ibl_plr WHERE teamname = '$teamlogo' AND tid = $tid AND retired = '0' AND ordinal <= 960 ORDER BY ordinal ASC"; // 960 is the cut-off ordinal for players on waivers
    $playersOnTeam = $db->sql_query($queryPlayersOnTeam);

    if ($useset == null) {
        $useset = 1;
    }

    $querySelectedOffenseSet = "SELECT * FROM ibl_offense_sets WHERE TeamName = '$teamlogo' AND SetNumber = '$useset'";
    $resultSelectedOffenseSet = $db->sql_query($querySelectedOffenseSet);
    $offenseSet = $db->sql_fetchrow($resultSelectedOffenseSet);

    $offense_name = $offenseSet['offense_name'];
    $Slot1 = $offenseSet['PG_Depth_Name'];
    $Slot2 = $offenseSet['SG_Depth_Name'];
    $Slot3 = $offenseSet['SF_Depth_Name'];
    $Slot4 = $offenseSet['PF_Depth_Name'];
    $Slot5 = $offenseSet['C_Depth_Name'];

    $Low1 = 1;
    $Low2 = 1;
    $Low3 = 1;
    $Low4 = 1;
    $Low5 = 1;

    $High1 = 9;
    $High2 = 9;
    $High3 = 9;
    $High4 = 9;
    $High5 = 9;

    echo "SELECT OFFENSIVE SET TO USE: ";

    $i = 0;

    while ($i < 3) {
        $name_of_set = $db->sql_result($result7, $i, "offense_name");
        $i++;

        echo "<a href=\"modules.php?name=Depth_Chart_Entry&useset=$i\">$name_of_set</a> | ";
    }

    echo "<hr>
		<form name=\"Depth_Chart\" method=\"post\" action=\"modules.php?name=Depth_Chart_Entry&op=submit\">
		    <input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
            <input type=\"hidden\" name=\"Set_Name\" value=\"$offense_name\">
		<center><img src=\"images/logo/$tid.jpg\"><br>";

    $table_ratings = $sharedFunctions->ratings($db, $playersOnTeam, $color1, $color2, $tid, "");
    echo $table_ratings;

    echo "<p><table>
        <tr>
            <th colspan=14><center>DEPTH CHART ENTRY - Offensive Set: $offense_name</center></th>
        </tr>
        <tr>
            <th>Pos</th>
            <th>Player</th>
            <th>$Slot1</th>
            <th>$Slot2</th>
            <th>$Slot3</th>
            <th>$Slot4</th>
            <th>$Slot5</th>
            <th>active</th>
            <th>min</th>
            <th>OF</th>
            <th>DF</th>
            <th>OI</th>
            <th>DI</th>
            <th>BH</th>
        </tr>";
    $depthcount = 1;

    mysqli_data_seek($playersOnTeam, 0);
    while ($player = $db->sql_fetchrow($playersOnTeam)) {
        $player_pid = $player['pid'];
        $player_pos = $player['pos'];
        $player_name = $player['name'];
        $player_staminacap = $player['sta'] + 40;
        if ($player_staminacap > 40) {
            $player_staminacap = 40;
        }

        $player_PG = $player['dc_PGDepth'];
        $player_SG = $player['dc_SGDepth'];
        $player_SF = $player['dc_SFDepth'];
        $player_PF = $player['dc_PFDepth'];
        $player_C = $player['dc_CDepth'];
        $player_active = $player['dc_active'];
        $player_min = $player['dc_minutes'];
        $player_of = $player['dc_of'];
        $player_df = $player['dc_df'];
        $player_oi = $player['dc_oi'];
        $player_di = $player['dc_di'];
        $player_bh = $player['dc_bh'];
        $player_inj = $player['injured'];

        if ($player_pos == 'PG') {
            $pos_value = 1;
        }

        if ($player_pos == 'G') {
            $pos_value = 2;
        }

        if ($player_pos == 'SG') {
            $pos_value = 3;
        }

        if ($player_pos == 'GF') {
            $pos_value = 4;
        }

        if ($player_pos == 'SF') {
            $pos_value = 5;
        }

        if ($player_pos == 'F') {
            $pos_value = 6;
        }

        if ($player_pos == 'PF') {
            $pos_value = 7;
        }

        if ($player_pos == 'FC') {
            $pos_value = 8;
        }

        if ($player_pos == 'C') {
            $pos_value = 9;
        }

        echo " <tr>
            <td>$player_pos</td>
			<td nowrap>
                <input type=\"hidden\" name=\"Injury$depthcount\" value=\"$player_inj\">
                <input type=\"hidden\" name=\"Name$depthcount\" value=\"$player_name\">
                <a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a>
            </td>";

        if ($pos_value >= $Low1 && $player_inj < 15) {
            if ($pos_value <= $High1) {
                echo "<td><select name=\"pg$depthcount\">";
                posHandler($player_PG);
                echo "</select></td>";
            } else {
                echo "<td><input type=\"hidden\" name=\"pg$depthcount\" value=\"0\">no</td>";
            }
        } else {
            echo "<td><input type=\"hidden\" name=\"pg$depthcount\" value=\"0\">no</td>";
        }

        if ($pos_value >= $Low2 && $player_inj < 15) {
            if ($pos_value <= $High2) {
                echo "<td><select name=\"sg$depthcount\">";
                posHandler($player_SG);
                echo "</select></td>";
            } else {
                echo "<td><input type=\"hidden\" name=\"sg$depthcount\" value=\"0\">no</td>";
            }
        } else {
            echo "<td><input type=\"hidden\" name=\"sg$depthcount\" value=\"0\">no</td>";
        }

        if ($pos_value >= $Low3 && $player_inj < 15) {
            if ($pos_value <= $High3) {
                echo "<td><select name=\"sf$depthcount\">";
                posHandler($player_SF);
                echo "</select></td>";
            } else {
                echo "<td><input type=\"hidden\" name=\"sf$depthcount\" value=\"0\">no</td>";
            }
        } else {
            echo "<td><input type=\"hidden\" name=\"sf$depthcount\" value=\"0\">no</td>";
        }

        if ($pos_value >= $Low4 && $player_inj < 15) {
            if ($pos_value <= $High4) {
                echo "<td><select name=\"pf$depthcount\">";
                posHandler($player_PF);
                echo "</select></td>";
            } else {
                echo "<td><input type=\"hidden\" name=\"pf$depthcount\" value=\"0\">no</td>";
            }
        } else {
            echo "<td><input type=\"hidden\" name=\"pf$depthcount\" value=\"0\">no</td>";
        }

        if ($pos_value >= $Low5 && $player_inj < 15) {
            if ($pos_value <= $High5) {
                echo "<td><select name=\"c$depthcount\">";
                posHandler($player_C);
                echo "</select></td>";
            } else {
                echo "<td><input type=\"hidden\" name=\"c$depthcount\" value=\"0\">no</td>";
            }
        } else {
            echo "<td><input type=\"hidden\" name=\"c$depthcount\" value=\"0\">no</td>";
        }

        echo "<td><select name=\"active$depthcount\">";
        if ($player_active == 1) {
            echo "<option value=\"1\" SELECTED>Yes</option><option value=\"0\">No</option>";
        } else {
            echo "<option value=\"1\">Yes</option><option value=\"0\" SELECTED>No</option>";
        }
        echo "</select></td>";

        echo "<td><select name=\"min$depthcount\">";
        echo "<option value=\"0\"" . ($player_min == 0 ? " SELECTED" : "") . ">Auto</option>";
        $abc = 1;
        while ($abc <= $player_staminacap) {
            echo "<option value=\"" . $abc . "\"" . ($player_min == $abc ? " SELECTED" : "") . ">" . $abc . "</option>";
            $abc++;
        }

        echo "</select></td><td><select name=\"OF$depthcount\">";
        offdefHandler($player_of);
        echo "</select></td><td><select name=\"DF$depthcount\">";
        offdefHandler($player_df);

        echo "</select></td><td><select name=\"OI$depthcount\">";
        oidibhHandler($player_oi);
        echo "</select></td><td><select name=\"DI$depthcount\">";
        oidibhHandler($player_di);
        echo "</select></td><td><select name=\"BH$depthcount\">";
        oidibhHandler($player_bh);

        echo "</select></td></tr>";
        $depthcount++;
    }

    echo "<tr>
        <th colspan=14><input type=\"radio\" checked> Submit Depth Chart? <input type=\"submit\" value=\"Submit\"></th>
    </tr></form></table></center>";

    CloseTable();

    // === END INSERT OF IBL DEPTH CHART ===

    include "footer.php";
}

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        include "header.php";
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        include "footer.php";
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

function submit()
{
    global $db, $sharedFunctions;

    include "header.php";
    OpenTable();

    $Set_Name = $_POST['Set_Name'];
    $Team_Name = $_POST['Team_Name'];
    $html = "$Team_Name Depth Chart Submission<br><table>";
    $html = $html . "<tr>
		<td><b>Name</td>
		<td><b>PG</td>
		<td><b>SG</td>
		<td><b>SF</td>
		<td><b>PF</td>
		<td><b>C</td>
		<td><b>Active</td>
		<td><b>Min</td>
		<td><b>OF</td>
		<td><b>DF</td>
		<td><b>OI</td>
		<td><b>DI</td>
		<td><b>BH</td>
	</tr>";
    $filetext = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI
";

    $activePlayers = 0;
    $pos_1 = 0;
    $pos_2 = 0;
    $pos_3 = 0;
    $pos_4 = 0;
    $pos_5 = 0;
    $hasStarterAtMultiplePositions = false;

    $i = 1;
    while ($i <= 15) {
        $startingPositionCount = 0;

        $a = "<tr><td>" . $_POST['Name' . $i] . "</td>";
        $b = "<td>" . $_POST['pg' . $i] . "</td>";
        $c = "<td>" . $_POST['sg' . $i] . "</td>";
        $d = "<td>" . $_POST['sf' . $i] . "</td>";
        $e = "<td>" . $_POST['pf' . $i] . "</td>";
        $f = "<td>" . $_POST['c' . $i] . "</td>";
        $g = "<td>" . $_POST['active' . $i] . "</td>";
        $h = "<td>" . $_POST['min' . $i] . "</td>";
        $z = "<td>" . $_POST['OF' . $i] . "</td>";
        $j = "<td>" . $_POST['DF' . $i] . "</td>";
        $k = "<td>" . $_POST['OI' . $i] . "</td>";
        $l = "<td>" . $_POST['DI' . $i] . "</td>";
        $m = "<td>" . $_POST['BH' . $i] . "</td></tr>
";

        $html = $html . $a . $b . $c . $d . $e . $f . $g . $h . $z . $j . $k . $l . $m;

        $injury = $_POST['Injury' . $i];
        $aa = $_POST['Name' . $i] . ",";
        $bb = $_POST['pg' . $i] . ",";
        $cc = $_POST['sg' . $i] . ",";
        $dd = $_POST['sf' . $i] . ",";
        $ee = $_POST['pf' . $i] . ",";
        $ff = $_POST['c' . $i] . ",";
        $gg = $_POST['active' . $i] . ",";
        $hh = $_POST['min' . $i] . ",";
        $zz = $_POST['OF' . $i] . ",";
        $jj = $_POST['DF' . $i] . ",";
        $kk = $_POST['OI' . $i] . ",";
        $ll = $_POST['DI' . $i] . ",";
        $mm = $_POST['BH' . $i] . "
";

        $filetext = $filetext . $aa . $bb . $cc . $dd . $ee . $ff . $gg . $hh . $zz . $jj . $kk . $ll . $mm;

        $dc_insert1 = $_POST['pg' . $i];
        $dc_insert2 = $_POST['sg' . $i];
        $dc_insert3 = $_POST['sf' . $i];
        $dc_insert4 = $_POST['pf' . $i];
        $dc_insert5 = $_POST['c' . $i];
        $dc_insert6 = $_POST['active' . $i];
        $dc_insert7 = $_POST['min' . $i];
        $dc_insert8 = $_POST['OF' . $i];
        $dc_insert9 = $_POST['DF' . $i];
        $dc_insertA = $_POST['OI' . $i];
        $dc_insertB = $_POST['DI' . $i];
        $dc_insertC = $_POST['BH' . $i];
        $dc_insertkey = addslashes($_POST['Name' . $i]);

        $updatequery1 = "UPDATE ibl_plr SET dc_PGDepth = '$dc_insert1' WHERE name = '$dc_insertkey'";
        $updatequery2 = "UPDATE ibl_plr SET dc_SGDepth = '$dc_insert2' WHERE name = '$dc_insertkey'";
        $updatequery3 = "UPDATE ibl_plr SET dc_SFDepth = '$dc_insert3' WHERE name = '$dc_insertkey'";
        $updatequery4 = "UPDATE ibl_plr SET dc_PFDepth = '$dc_insert4' WHERE name = '$dc_insertkey'";
        $updatequery5 = "UPDATE ibl_plr SET dc_CDepth = '$dc_insert5' WHERE name = '$dc_insertkey'";
        $updatequery6 = "UPDATE ibl_plr SET dc_active = '$dc_insert6' WHERE name = '$dc_insertkey'";
        $updatequery7 = "UPDATE ibl_plr SET dc_minutes = '$dc_insert7' WHERE name = '$dc_insertkey'";
        $updatequery8 = "UPDATE ibl_plr SET dc_of = '$dc_insert8' WHERE name = '$dc_insertkey'";
        $updatequery9 = "UPDATE ibl_plr SET dc_df = '$dc_insert9' WHERE name = '$dc_insertkey'";
        $updatequeryA = "UPDATE ibl_plr SET dc_oi = '$dc_insertA' WHERE name = '$dc_insertkey'";
        $updatequeryB = "UPDATE ibl_plr SET dc_di = '$dc_insertB' WHERE name = '$dc_insertkey'";
        $updatequeryC = "UPDATE ibl_plr SET dc_bh = '$dc_insertC' WHERE name = '$dc_insertkey'";
        $updatequeryD = "UPDATE ibl_team_history SET depth = NOW() WHERE team_name = '$Team_Name'";
        $updatequeryF = "UPDATE ibl_team_history SET sim_depth = NOW() WHERE team_name = '$Team_Name'";
        $executeupdate1 = $db->sql_query($updatequery1);
        $executeupdate2 = $db->sql_query($updatequery2);
        $executeupdate3 = $db->sql_query($updatequery3);
        $executeupdate4 = $db->sql_query($updatequery4);
        $executeupdate5 = $db->sql_query($updatequery5);
        $executeupdate6 = $db->sql_query($updatequery6);
        $executeupdate7 = $db->sql_query($updatequery7);
        $executeupdate8 = $db->sql_query($updatequery8);
        $executeupdate9 = $db->sql_query($updatequery9);
        $executeupdateA = $db->sql_query($updatequeryA);
        $executeupdateB = $db->sql_query($updatequeryB);
        $executeupdateC = $db->sql_query($updatequeryC);

        if ($dc_insert6 == 1) {
            $activePlayers++;
        }

        if ($dc_insert1 > 0 && $injury == 0) {
            $pos_1++;
        }

        if ($dc_insert2 > 0 && $injury == 0) {
            $pos_2++;
        }

        if ($dc_insert3 > 0 && $injury == 0) {
            $pos_3++;
        }

        if ($dc_insert4 > 0 && $injury == 0) {
            $pos_4++;
        }

        if ($dc_insert5 > 0 && $injury == 0) {
            $pos_5++;
        }

        // Check whether a player is listed at multiple starting positions
        if ($_POST['pg' . $i] == 1) {
            $startingPositionCount++;
        }

        if ($_POST['sg' . $i] == 1) {
            $startingPositionCount++;
        }

        if ($_POST['sf' . $i] == 1) {
            $startingPositionCount++;
        }

        if ($_POST['pf' . $i] == 1) {
            $startingPositionCount++;
        }

        if ($_POST['c' . $i] == 1) {
            $startingPositionCount++;
        }

        if ($startingPositionCount > 1) {
            $hasStarterAtMultiplePositions = true;
            $nameOfProblemStarter = $_POST['Name' . $i];
        }

        $i++;
    }

    $html = $html . "</table>";

    $seasonPhase = $sharedFunctions->getCurrentSeasonPhase();
    if ($seasonPhase != 'Playoffs') {
        $minActivePlayers = 12;
        $maxActivePlayers = 12;
        $minPositionDepth = 3;
    } else {
        $minActivePlayers = 10;
        $maxActivePlayers = 12;
        $minPositionDepth = 2;
    }

    if ($activePlayers < $minActivePlayers) {
        $errorText .= "<font color=red><b>You must have at least $minActivePlayers active players in your lineup; you have $activePlayers.</b></font><p>
			Please press the \"Back\" button on your browser and activate " . ($minActivePlayers - $activePlayers) . " player(s).</center><p>";
        $error = true;
    }
    if ($activePlayers > $maxActivePlayers) {
        $errorText .= "<font color=red><b>You can't have more than $maxActivePlayers active players in your lineup; you have $activePlayers.</b></font><p>
			Please press the \"Back\" button on your browser and deactivate " . ($activePlayers - $maxActivePlayers) . " player(s).</center><p>";
        $error = true;
    }
    if ($pos_1 < $minPositionDepth) {
        $errorText .= "<font color=red><b>You must have at least $minPositionDepth players entered in PG slot &mdash; you have $pos_1.</b></font><p>
			Please click the \"Back\" button on your browser and activate " . ($minPositionDepth - $pos_1) . " player(s).</center><p>";
        $error = true;
    }
    if ($pos_2 < $minPositionDepth) {
        $errorText .= "<font color=red><b>You must have at least $minPositionDepth players entered in SG slot &mdash; you have $pos_2.</b></font><p>
			Please click the \"Back\" button on your browser and activate " . ($minPositionDepth - $pos_2) . " player(s).</center><p>";
        $error = true;
    }
    if ($pos_3 < $minPositionDepth) {
        $errorText .= "<font color=red><b>You must have at least $minPositionDepth players entered in SF slot &mdash; you have $pos_3.</b></font><p>
			Please click the \"Back\" button on your browser and activate " . ($minPositionDepth - $pos_3) . " player(s).</center><p>";
        $error = true;
    }
    if ($pos_4 < $minPositionDepth) {
        $errorText .= "<font color=red><b>You must have at least $minPositionDepth players entered in PF slot &mdash; you have $pos_4.</b></font><p>
			Please click the \"Back\" button on your browser and activate " . ($minPositionDepth - $pos_4) . " player(s).</center><p>";
        $error = true;
    }
    if ($pos_5 < $minPositionDepth) {
        $errorText .= "<font color=red><b>You must have at least $minPositionDepth players entered in C slot &mdash; you have $pos_5.</b></font><p>
			Please click the \"Back\" button on your browser and activate " . ($minPositionDepth - $pos_5) . " player(s).</center><p>";
        $error = true;
    }
    if ($hasStarterAtMultiplePositions == true) {
        $errorText .= "<font color=red><b>$nameOfProblemStarter is listed at more than one position in the starting lineup.</b></font><p>
			Please click the \"Back\" button on your browser and ensure they are only starting at ONE position.</center><p>";
        $error = true;
    }

    if ($error == true) {
        echo "<center><u>Your lineup has <b>not</b> been submitted:</u></center><br>";
        echo $errorText;
    } else {
        $emailsubject = $Team_Name . " Depth Chart - $Set_Name Offensive Set";
        $recipient = 'ibldepthcharts@gmail.com';
        $filename = 'depthcharts/' . $Team_Name . '.txt';

        if (file_put_contents($filename, $filetext)) {
            $executeupdateD = $db->sql_query($updatequeryD);
            $executeupdateF = $db->sql_query($updatequeryF);

            if ($_SERVER['SERVER_NAME'] != "localhost") {
                mail($recipient, $emailsubject, $filetext, "From: ibldepthcharts@gmail.com");
            }

            echo "<center><u>Your depth chart has been submitted and e-mailed successfully. Thank you.</u></center><p>";
        } else {
            echo "<font color=red>Depth chart failed to save properly; please contact the commissioner.</font></center><p>";
        }
    }

    echo "<br>$html";
    // DISPLAYS DEPTH CHART

    CloseTable();
    include "footer.php";
}

switch ($op) {
    case "submit":
        submit();
        break;
    default:
        main($user);
        break;
}
