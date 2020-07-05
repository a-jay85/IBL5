<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2006 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

require $_SERVER['DOCUMENT_ROOT'] . '/sharedFunctions.php';
require $_SERVER['DOCUMENT_ROOT'] . '/discordWebhooks.php';

$pagetitle = "- Team Pages";

function waivers($user)
{
    global $stop, $action;
    if (!is_user($user)) {
        include("header.php");
        if ($stop) {
            OpenTable();
            displaytopmenu($tid);
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        } else {
            OpenTable();
            displaytopmenu($tid);
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        }
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        include("footer.php");
    } elseif (is_user($user)) {
        if (getCurrentSeasonPhase() != "Free Agency") {
            global $cookie;
            cookiedecode($user);
            waiverexecute($cookie[1], $action);
        } else {
            include ("header.php");
            OpenTable();
            displaytopmenu($tid);
            echo "Sorry, but players may not be added from or dropped to waivers at the present time.";
            CloseTable();
            include ("footer.php");
        }
    }
}

function waiverexecute($username, $action, $bypass=0, $hid=0, $url=0)
{
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $subscription_url, $partner, $action;
    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) $board_config[$row['config_name']] = $row['config_value'];
    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) cookiedecode($user);
    include("header.php");

    ////////// WAIVER ADDITIONS/CUTS

    $Team_Offering = $_POST['Team_Name'];
    $Type_Of_Action = $_POST['Action'];
    $Player_to_Process = $_POST['Player_ID'];
    $Fields_Counter = $_POST['counterfields'];
    $Roster_Slots = $_POST['rosterslots'];
    $Healthy_Roster_Slots = $_POST['healthyrosterslots'];

    if ($Type_Of_Action == 'add' OR $Type_Of_Action == 'drop') {
        $queryt = "SELECT * FROM nuke_ibl_team_info WHERE team_name = '$Team_Offering'";
        $resultt = mysql_query($queryt);

        $teamid = mysql_result($resultt, 0, "teamid");

        $Timestamp = intval(time());

        // ADD TEAM TOTAL SALARY FOR THIS YEAR

        $querysalary = "SELECT * FROM nuke_iblplyr WHERE teamname = '$Team_Offering' AND retired = 0";
        $results = mysql_query($querysalary);
        $num = mysql_numrows($results);
        $z = 0;
        while ($z < $num) {
            $cy = mysql_result($results, $z, "cy");
            $xcyx = "cy$cy";
            $cy2 = mysql_result($results, $z, "$xcyx");
            $TotalSalary = $TotalSalary + $cy2;
            $z++;
        }

        // END TEAM TOTAL SALARY FOR THIS YEAR

        $k = 0;

        $waiverquery = "SELECT * FROM nuke_iblplyr WHERE pid = '$Player_to_Process'";
        $waiverresult = mysql_query($waiverquery);
        $playername = mysql_result($waiverresult, 0, "name");
        $players_team = mysql_result($waiverresult, 0, "tid");
        $cy1 = mysql_result($waiverresult, 0, "cy1");
        $cy2 = mysql_result($waiverresult, 0, "cy2");
        $cy3 = mysql_result($waiverresult, 0, "cy3");
        $cy4 = mysql_result($waiverresult, 0, "cy4");
        $cy5 = mysql_result($waiverresult, 0, "cy5");
        $cy6 = mysql_result($waiverresult, 0, "cy6");
        $player_exp = mysql_result($waiverresult, 0, "exp");

        if ($Type_Of_Action == 'drop') {
            if ($Roster_Slots > 2 and $TotalSalary > 7000) { // TODO: Change 7000 to hard cap variable
                $errortext = "You have 12 players and are over $70 mill hard cap.  Therefore you can't drop a player!";
            } else {
                $queryi = "UPDATE nuke_iblplyr SET `ordinal` = '1000', `droptime` = '$Timestamp' WHERE `pid` = '$Player_to_Process' LIMIT 1;";
                $resulti = mysql_query($queryi);

                $topicid = 32;
                $storytitle = $Team_Offering . " make waiver cuts";
                $hometext = "The " . $Team_Offering . " cut " . $playername . " to waivers.";

                // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE
                $timestamp = date('Y-m-d H:i:s',time());

                $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                $resultcat = mysql_query($querycat);
                $WPMoves = mysql_result($resultcat, 0, "counter");
                $catid = mysql_result($resultcat, 0, "catid");

                $WPMoves = $WPMoves + 1;

                $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                $resultcat2 = mysql_query($querycat2);

                $querystor = "INSERT INTO nuke_stories
                        (catid,
                         aid,
                         title,
                         time,
                         hometext,
                         topic,
                         informant,
                         counter,
                         alanguage)
                    VALUES
                        ('$catid',
                         'Associated Press',
                         '$storytitle',
                         '$timestamp',
                         '$hometext',
                         '$topicid',
                         'Associated Press',
                         '0',
                         'english') ";
                $resultstor = mysql_query($querystor);

                postToDiscordChannel('#waiver-wire', $hometext);

                $errortext = "Your waiver move should now be processed. $playername has been cut to waivers.";
            }
        } else if ($Type_Of_Action == 'add') {
            if ($cy1 == '' OR $cy1 == 0) {
                if ($player_exp > 9) {
                    $cy1 = 103;
                } elseif ($player_exp > 8) {
                    $cy1 = 100;
                } elseif ($player_exp > 7) {
                    $cy1 = 89;
                } elseif ($player_exp > 6) {
                    $cy1 = 82;
                } elseif ($player_exp > 5) {
                    $cy1 = 76;
                } elseif ($player_exp > 4) {
                    $cy1 = 70;
                } elseif ($player_exp > 3) {
                    $cy1 = 64;
                } elseif ($player_exp > 2) {
                    $cy1 = 61;
                } elseif ($player_exp > 1) {
                    $cy1 = 51;
                } else {
                    $cy1 = 35;
                }
            }

            if ($Healthy_Roster_Slots < 4 AND $TotalSalary + $cy1 > 7000) { // TODO: Change 7000 to hard cap variable
                $errortext = "You have 12 or more healthy players and this signing will put you over $70 million. Therefore you cannot make this signing.";
            } elseif ($Healthy_Roster_Slots > 3 AND $TotalSalary + $cy1 > 7000 AND $cy1 > 103) { // TODO: Change 7000 to hard cap variable
                $errortext = "You are over the hard cap and therefore can only sign players who are making veteran minimum!";
            } elseif ($Healthy_Roster_Slots < 1) {
                $errortext = "You have full roster of 15 players. You can't sign another player at this time!";
            } else {
                $queryi = "UPDATE nuke_iblplyr SET `ordinal` = '800', `cy` = 1, `cy1` = $cy1, `teamname` = '$Team_Offering', `tid` = '$teamid' WHERE `pid` = '$Player_to_Process' LIMIT 1;";
                $resulti = mysql_query($queryi);
                $Roster_Slots++;

                $topicid = 33;
                $storytitle = $Team_Offering . " make waiver additions";
                $hometext = "The " . $Team_Offering . " sign " . $playername . " from waivers for $cy1.";

                // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

                $timestamp = date('Y-m-d H:i:s', time());

                $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                $resultcat = mysql_query($querycat);
                $WPMoves = mysql_result($resultcat, 0, "counter");
                $catid = mysql_result($resultcat, 0, "catid");

                $WPMoves = $WPMoves + 1;

                $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                $resultcat2 = mysql_query($querycat2);

                $querystor = "INSERT INTO nuke_stories
                        (catid,
                         aid,
                         title,
                         time,
                         hometext,
                         topic,
                         informant,
                         counter,
                         alanguage)
                    VALUES
                        ('$catid',
                         'Associated Press',
                         '$storytitle',
                         '$timestamp',
                         '$hometext',
                         '$topicid',
                         'Associated Press',
                         '0',
                         'english')";
                $resultstor = mysql_query($querystor);

                if (isset($resultstor)) {
                    $recipient = 'ibldepthcharts@gmail.com';
                    mail($recipient, $storytitle, $hometext, "From: ibldepthcharts@gmail.com");

                    postToDiscordChannel('#waiver-wire', $hometext);
                }

                $errortext = "Your waiver move should now be processed. $playername has been signed from waivers and added to your roster.";
            }
        } // END OF IF/ELSE BRACE FOR TYPE OF ACTION IS DROP OR ADD
    } // IF ELSE BRACE FOR IF TYPE OF ACTION FIELD IS NOT NULL; I.E., DROP OR ADD

    // === CODE TO EXECUTE WAIVER MOVES ABOVE ===

    OpenTable();

    $teamlogo = $userinfo[user_ibl_team];
	$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
	$tid = mysql_result(mysql_query($queryTeamID), 0);

    displaytopmenu($tid);

    echo "<center><font color=red><b>$errortext</b></font></center>";
    $sql7 = "SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC ";
    $result7 = $db->sql_query($sql7);

    $sql9 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' AND injured = '0' ORDER BY ordinal ASC ";
    $result9 = $db->sql_query($sql9);

    $healthyrosterslots = 15;

    while ($row9 = $db->sql_fetchrow($result9)) $healthyrosterslots--;

    $sql10 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' ORDER BY ordinal ASC ";
    $result10 = $db->sql_query($sql10);

    $rosterslots = 15;

    while ($row10 = $db->sql_fetchrow($result10)) $rosterslots--;

    if ($action == 'drop') {
        $sql8 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$userinfo[user_ibl_team]' AND retired = '0' AND ordinal < '961' ORDER BY ordinal ASC ";
        $result8 = $db->sql_query($sql8);
    } else {
        $sql8 = "SELECT * FROM nuke_iblplyr WHERE ordinal > '959' AND retired = '0' ORDER BY ordinal ASC ";
        $result8 = $db->sql_query($sql8);
    }

    echo "<hr><form name=\"Waiver_Move\" method=\"post\" action=\"\"><input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">";
    echo "<input type=\"hidden\" name=\"Action\" value=\"$action\">";

    echo "<center><img src=\"images/logo/$tid.jpg\"><br><table border=1 cellspacing=0 cellpadding=0>
        <tr>
            <th colspan=3><center>WAIVER WIRE - YOUR TEAM CURRENTLY HAS $rosterslots EMPTY ROSTER SPOTS and $healthyrosterslots HEALTHY ROSTER SPOTS</center></th>
        </tr>
        <tr>
            <td valign=top><center><b><u>$userinfo[user_ibl_team]</u></b></center>
            <select name=\"Player_ID\"><option value=\"\">Select player...</option>";

    $k = 0;
    $timenow = intval(time());

    while ($row8 = $db->sql_fetchrow($result8)) {
        $wait_time = '';
        $player_pos = $row8[altpos];
        $player_name = $row8[name];
        $player_pid = $row8[pid];
        $cy = $row8[cy];
        $xcyy = "cy$cy";
        $player_exp = $row8[exp];
        $zcy2 = $row8[$xcyy];

        if ($zcy2 == '' AND $zcy2 == 0) {
            if ($player_exp > 9) {
                $zcy2 = 103;
            } elseif ($player_exp > 8) {
                $zcy2 = 100;
            } elseif ($player_exp > 7) {
                $zcy2 = 89;
            } elseif ($player_exp > 6) {
                $zcy2 = 82;
            } elseif ($player_exp > 5) {
                $zcy2 = 76;
            } elseif ($player_exp > 4) {
                $zcy2 = 70;
            } elseif ($player_exp > 3) {
                $zcy2 = 64;
            } elseif ($player_exp > 2) {
                $zcy2 = 61;
            } elseif ($player_exp > 1) {
                $zcy2 = 51;
            } else {
                $zcy2 = 35;
            }
        }
        $nocheckbox = 0;

        if ($action == 'add') {
            $player_droptime = $row8[droptime];
            $time_diff = $timenow - $player_droptime;

            if ($time_diff < 86400) {
                $wait_time = 86400 - $time_diff;
                $time_hours = floor($wait_time / 3600);
                $time_minutes = floor(($wait_time - $time_hours * 3600) / 60);
                $time_seconds = ($wait_time % 60);
                $wait_time = '(Clears in ' . $time_hours . ' h, ' . $time_minutes . ' m, ' . $time_seconds . ' s)';
                $nocheckbox = 1;
            }
        }
        $dropdown = $dropdown . "
        <option value=\"$player_pid\">$player_pos $player_name $wait_time $zcy2</option>";
        //        echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"cy$k\" value=\"$cy2\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
        /*
                if ($nocheckbox == 1)
                {
                echo "** $player_pos $player_name $wait_time
        <br>";
                } else {
                echo "<input type=\"checkbox\" name=\"check$k\"> $player_pos $player_name $wait_time $$cy2
        <br>";
                }
        */
        $k++;
    }

    echo "$dropdown</select><input type=\"hidden\" name=\"counterfields\" value=\"$k\"><input type=\"hidden\" name=\"rosterslots\" value=\"$rosterslots\"></td>
    ";
    echo "<input type=\"hidden\" name=\"healthyrosterslots\" value=\"$healthyrosterslots\"></td>
    ";
    echo "</td></tr><tr><td colspan=3><center><input type=\"submit\" value=\"Click to $action player(s) to/from Waiver Pool\"></td></tr></form></center></table></center>";
    CloseTable();

    // === END INSERT OF TRADE STUFF ===

    include("footer.php");
}

waivers($user, $action);
/*
switch($op) {
    case "waivers":
    waivers($user, $action);
    break;

    default:
    menu();
    break;
}
*/
?>
