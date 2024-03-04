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
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function waivers($user)
{
    global $db, $stop, $action;
    $sharedFunctions = new Shared($db);

    if (!is_user($user)) {
        include "header.php";
        if ($stop) {
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        } else {
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        }
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        include "footer.php";
    } elseif (is_user($user)) {
        $currentSeasonPhase = $sharedFunctions->getCurrentSeasonPhase();
        $allowWaiverMoves = $sharedFunctions->getWaiverWireStatus();

        if (
            ($currentSeasonPhase == "Preseason" AND $allowWaiverMoves == "Yes")
            OR $currentSeasonPhase == "HEAT"
            OR $currentSeasonPhase == "Regular Season"
            OR $currentSeasonPhase == "Playoffs"
        ) {
            global $cookie;
            cookiedecode($user);
            waiverexecute($cookie[1], $action);
        } else {
            include "header.php";
            OpenTable();
            $sharedFunctions->displaytopmenu($tid);
            echo "Sorry, but players may not be added from or dropped to waivers at the present time.";
            CloseTable();
            include "footer.php";
        }
    }
}

function waiverexecute($username, $action, $bypass = 0, $hid = 0, $url = 0)
{
    global $user, $prefix, $user_prefix, $db, $action;
    $sharedFunctions = new Shared($db);

    $sql = "SELECT * FROM " . $prefix . "_bbconfig";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $board_config[$row['config_name']] = $row['config_value'];
    }

    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    include "header.php";

    $Team_Offering = $_POST['Team_Name'];
    $Type_Of_Action = $_POST['Action'];
    $Player_to_Process = $_POST['Player_ID'];
    $Roster_Slots = $_POST['rosterslots'];
    $Healthy_Roster_Slots = $_POST['healthyrosterslots'];

    if ($Type_Of_Action == 'add' or $Type_Of_Action == 'drop') {
        $queryt = "SELECT * FROM ibl_team_info WHERE team_name = '$Team_Offering'";
        $resultt = $db->sql_query($queryt);

        $teamid = $db->sql_result($resultt, 0, "teamid");

        $Timestamp = intval(time());

        // ADD TEAM TOTAL SALARY FOR THIS YEAR

        $querysalary = "SELECT * FROM ibl_plr WHERE teamname = '$Team_Offering' AND retired = 0";
        $results = $db->sql_query($querysalary);
        $num = $db->sql_numrows($results);
        $z = 0;
        while ($z < $num) {
            $cy = $db->sql_result($results, $z, "cy");
            $xcyx = "cy$cy";
            $cy2 = $db->sql_result($results, $z, "$xcyx");
            $TotalSalary = $TotalSalary + $cy2;
            $z++;
        }

        // END TEAM TOTAL SALARY FOR THIS YEAR

        $k = 0;

        $waiverquery = "SELECT * FROM ibl_plr WHERE pid = '$Player_to_Process'";
        $waiverresult = $db->sql_query($waiverquery);
        $playername = $db->sql_result($waiverresult, 0, "name");
        $players_team = $db->sql_result($waiverresult, 0, "tid");
        $cy1 = $db->sql_result($waiverresult, 0, "cy1");
        $cy2 = $db->sql_result($waiverresult, 0, "cy2");
        $cy3 = $db->sql_result($waiverresult, 0, "cy3");
        $cy4 = $db->sql_result($waiverresult, 0, "cy4");
        $cy5 = $db->sql_result($waiverresult, 0, "cy5");
        $cy6 = $db->sql_result($waiverresult, 0, "cy6");
        $player_exp = $db->sql_result($waiverresult, 0, "exp");

        if ($Type_Of_Action == 'drop') {
            if ($Roster_Slots > 2 and $TotalSalary > 7000) { // TODO: Change 7000 to hard cap variable
                $errortext = "You have 12 players and are over $70 mill hard cap.  Therefore you can't drop a player!";
            } else {
                $queryi = "UPDATE ibl_plr SET `ordinal` = '1000', `droptime` = '$Timestamp' WHERE `pid` = '$Player_to_Process' LIMIT 1;";
                $resulti = $db->sql_query($queryi);

                $topicid = 32;
                $storytitle = $Team_Offering . " make waiver cuts";
                $hometext = "The " . $Team_Offering . " cut " . $playername . " to waivers.";

                // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE
                $timestamp = date('Y-m-d H:i:s', time());

                $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                $resultcat = $db->sql_query($querycat);
                $WPMoves = $db->sql_result($resultcat, 0, "counter");
                $catid = $db->sql_result($resultcat, 0, "catid");

                $WPMoves++;

                $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                $resultcat2 = $db->sql_query($querycat2);

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
                $resultstor = $db->sql_query($querystor);

                Discord::postToChannel('#waiver-wire', $hometext);

                $errortext = "Your waiver move should now be processed. $playername has been cut to waivers.";
            }
        } else if ($Type_Of_Action == 'add') {
            if ($cy1 == '' or $cy1 == 0) {
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
                } else {
                    $cy1 = 51;
                }
                $newWaiverContract = true;
            }

            if ($Player_to_Process == NULL OR $Player_to_Process == "") {
                $errortext = "You didn't select a valid player. Please select a player and try again.";
            } elseif ($Healthy_Roster_Slots < 4 and $TotalSalary + $cy1 > 7000) { // TODO: Change 7000 to hard cap variable
                $errortext = "You have 12 or more healthy players and this signing will put you over $70 million. Therefore you cannot make this signing.";
            } elseif ($Healthy_Roster_Slots > 3 and $TotalSalary + $cy1 > 7000 and $cy1 > 103) { // TODO: Change 7000 to hard cap variable
                $errortext = "You are over the hard cap and therefore can only sign players who are making veteran minimum!";
            } elseif ($Healthy_Roster_Slots < 1) {
                $errortext = "You have full roster of 15 players. You can't sign another player at this time!";
            } else {
                $queryi = "UPDATE ibl_plr
                    SET `ordinal` = '800',
                        `bird` = 0, ";
                if ($newWaiverContract == true) {
                    $queryi .= "`cy1` = $cy1,
                                `cy` = 1, ";
                    $finalContract = $cy1;
                } else {
                    $currentContractYear = $db->sql_result($waiverresult, 0, "cy");
                    $contractLengthInYears = $db->sql_result($waiverresult, 0, "cyt");
                    while ($currentContractYear <= $contractLengthInYears) {
                        $contractYearIncrementor = "cy$currentContractYear";
                        $salaryForCurrentYear = $db->sql_result($waiverresult, 0, "$contractYearIncrementor");
                        $finalContract .= $salaryForCurrentYear . " ";
                        
                        $currentContractYear++;
                    }
                }
                $queryi .= "`teamname` = '$Team_Offering',
                            `tid` = '$teamid'
                    WHERE `pid` = '$Player_to_Process'
                    LIMIT 1;";

                if ($db->sql_query($queryi)) {
                    $Roster_Slots++;

                    // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE
                    $topicid = 33;
                    $storytitle = $Team_Offering . " make waiver additions";
                    $hometext = "The " . $Team_Offering . " sign " . $playername . " from waivers for $finalContract.";

                    $timestamp = date('Y-m-d H:i:s', time());

                    $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
                    $resultcat = $db->sql_query($querycat);
                    $WPMoves = $db->sql_result($resultcat, 0, "counter");
                    $catid = $db->sql_result($resultcat, 0, "catid");

                    $WPMoves++;

                    $querycat2 = "UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
                    $resultcat2 = $db->sql_query($querycat2);

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
                    $resultstor = $db->sql_query($querystor);

                    $recipient = 'ibldepthcharts@gmail.com';
                    mail($recipient, $storytitle, $hometext, "From: waivers@iblhoops.net");

                    Discord::postToChannel('#waiver-wire', $hometext);

                    $errortext = "Your waiver move should now be processed. $playername has been signed from waivers and added to your roster.";
                } else {
                    $errortext = "Oops, something went wrong. Post what you were trying to do in <A HREF=\"https://discord.com/channels/666986450889474053/671435182502576169\">#site-bugs-and-to-do</A> and we'll fix it asap. Sorry!";
                }

            }
        } // END OF IF/ELSE BRACE FOR TYPE OF ACTION IS DROP OR ADD
    } // IF ELSE BRACE FOR IF TYPE OF ACTION FIELD IS NOT NULL; I.E., DROP OR ADD

    // === CODE TO EXECUTE WAIVER MOVES ABOVE ===

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $queryTeamID = "SELECT teamid FROM ibl_team_info WHERE team_name = '$teamlogo'";
    $tid = $db->sql_result($db->sql_query($queryTeamID), 0);

    $sharedFunctions->displaytopmenu($tid);

    $querySelectHealthyPlayersOnUsersTeam = "
        SELECT *
        FROM ibl_plr
        WHERE teamname = '$userinfo[user_ibl_team]'
            AND retired = '0'
            AND ordinal <= '960'
            AND injured = '0'";
    $resultSelectHealthyPlayersOnUsersTeam = $db->sql_query($querySelectHealthyPlayersOnUsersTeam);
    $numhealthyPlayersOnUsersTeam = $db->sql_numrows($resultSelectHealthyPlayersOnUsersTeam);
    $healthyRosterSpots = 15;
    $healthyRosterSpots -= $numhealthyPlayersOnUsersTeam;

    $querySelectAllPlayersOnUsersTeam = "
        SELECT *
        FROM ibl_plr
        WHERE teamname = '$userinfo[user_ibl_team]'
            AND retired = '0'
            AND ordinal <= '960'
        ORDER BY name ASC";
    $resultSelectAllPlayersOnUsersTeam = $db->sql_query($querySelectAllPlayersOnUsersTeam);
    $numPlayersOnUsersTeam = $db->sql_numrows($resultSelectAllPlayersOnUsersTeam);
    $rosterSpots = 15;
    $rosterSpots -= $numPlayersOnUsersTeam;

    if ($action == 'drop') {
        $resultListOfPlayersForWaiverOperation = $db->sql_query($querySelectAllPlayersOnUsersTeam);
    } else {
        $queryAllPlayersOnWaivers = "SELECT * FROM ibl_plr WHERE ordinal > '960' AND retired = '0' AND name NOT LIKE '%|%' ORDER BY name ASC";
        $resultListOfPlayersForWaiverOperation = $db->sql_query($queryAllPlayersOnWaivers);
    }

    $k = 0;
    $timenow = intval(time());

    while ($playerForWaiverOperation = $db->sql_fetchrow($resultListOfPlayersForWaiverOperation)) {
        $wait_time = '';
        $player_name = $playerForWaiverOperation['name'];
        $player_pid = $playerForWaiverOperation['pid'];
        $currentContractYear = $playerForWaiverOperation['cy'];
        $contractLengthInYears = $playerForWaiverOperation['cyt'];
        $contractYearIncrementor = "cy$currentContractYear";
        $player_exp = $playerForWaiverOperation['exp'];
        $salaryForCurrentYear = $playerForWaiverOperation[$contractYearIncrementor];

        $fullContract = "";
        if ($salaryForCurrentYear == '' and $salaryForCurrentYear == 0) {
            if ($player_exp > 9) {
                $fullContract = 103;
            } elseif ($player_exp > 8) {
                $fullContract = 100;
            } elseif ($player_exp > 7) {
                $fullContract = 89;
            } elseif ($player_exp > 6) {
                $fullContract = 82;
            } elseif ($player_exp > 5) {
                $fullContract = 76;
            } elseif ($player_exp > 4) {
                $fullContract = 70;
            } elseif ($player_exp > 3) {
                $fullContract = 64;
            } elseif ($player_exp > 2) {
                $fullContract = 61;
            } else {
                $fullContract = 51;
            }
        } else {
            while ($currentContractYear <= $contractLengthInYears) {
                $contractYearIncrementor = "cy$currentContractYear";
                $salaryForCurrentYear = $playerForWaiverOperation[$contractYearIncrementor];
                $fullContract .= $salaryForCurrentYear . " ";
                
                $currentContractYear++;
            }
        }
        $nocheckbox = 0;

        if ($action == 'add') {
            $player_droptime = $playerForWaiverOperation['droptime'];
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
        <option value=\"$player_pid\">$player_name $fullContract $wait_time</option>";
        //        echo "<input type=\"hidden\" name=\"index$k\" value=\"$player_pid\"><input type=\"hidden\" name=\"cy$k\" value=\"$cy2\"><input type=\"hidden\" name=\"type$k\" value=\"1\">";
        /*
        if ($nocheckbox == 1)
        {
        echo "** $player_name $fullContract $wait_time
        <br>";
        } else {
        echo "<input type=\"checkbox\" name=\"check$k\">$player_name $fullContract $wait_time
        <br>";
        }
         */
        $k++;
    }

    echo "<center><font color=red><b>$errortext</b></font></center>";
    echo "<form name=\"Waiver_Move\" method=\"post\" action=\"\"><input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">";
    echo "<center><img src=\"images/logo/$tid.jpg\"><br><table border=1 cellspacing=0 cellpadding=0>
        <tr>
            <th colspan=3><center>WAIVER WIRE - YOUR TEAM CURRENTLY HAS $rosterSpots EMPTY ROSTER SPOTS and $healthyRosterSpots HEALTHY ROSTER SPOTS</center></th>
        </tr>
        <tr>
            <td valign=top><center><b><u>$userinfo[user_ibl_team]</u></b>
                <select name=\"Player_ID\"><option value=\"\">Select player...</option>
                    $dropdown
                </select></center>
            </td>
        </tr>";
    echo "<input type=\"hidden\" name=\"Action\" value=\"$action\">";
    echo "<input type=\"hidden\" name=\"rosterslots\" value=\"$rosterSpots\">";
    echo "<input type=\"hidden\" name=\"healthyrosterslots\" value=\"$healthyRosterSpots\">";
    echo "
        <tr>
            <td colspan=3><center><input type=\"submit\" value=\"Click to $action player(s) to/from Waiver Pool\"></center></td>
        </tr></form></table></center>";
    
    $team = Team::withTeamID($db, 35);
    $table_ratings = UI::ratings($db, $resultListOfPlayersForWaiverOperation, $team, "");
    echo $table_ratings;

    CloseTable();

    include "footer.php";
}

waivers($user, $action);
