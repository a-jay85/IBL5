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

$pagetitle = "- Player Archives";

function showmenu()
{
    NukeHeader::header();
    OpenTable();

    UI::playerMenu();

    CloseTable();
    include "footer.php";
}

function showpage($playerID, $spec)
{
    global $db, $cookie;
    $sharedFunctions = new Shared($db);
    $season = new Season($db);
    
    $player = Player::withPlayerID($db, $playerID);
    $playerStats = PlayerStats::withPlayerID($db, $playerID);
    $spec = intval($spec);

    $year = $player->draftYear + $player->yearsOfExperience; 

    // DISPLAY PAGE

    NukeHeader::header();
    OpenTable();
    UI::playerMenu();

    echo "<table>
        <tr>
            <td valign=top><font class=\"title\">$player->position $player->name ";

    if ($player->nickname != NULL) {
        echo "- Nickname: \"$player->nickname\" ";
    }

    echo "(<a href=\"modules.php?name=Team&op=team&tid=$player->teamID\">$player->teamName</a>)</font>
        <hr>
        <table>
            <tr>
                <td valign=center><img src=\"images/player/$playerID.jpg\" height=\"90\" width=\"65\"></td>
                <td>";

    // RENEGOTIATION BUTTON START

    $userTeamName = $sharedFunctions->getTeamnameFromUsername($cookie[1]);
    $userTeam = Team::withTeamName($db, $userTeamName);

    if ($player->wasRookieOptioned()) {
        echo "<table align=right bgcolor=#ff0000>
                <tr>
                    <td align=center>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>
                </tr>
            </table>";
    } elseif (
        $userTeam->name != "Free Agents"
        AND $userTeam->hasUsedExtensionThisSeason == 0
        AND $player->canRenegotiateContract()
        AND $player->teamName == $userTeam->name
        AND $season->phase != 'Draft'
        AND $season->phase != 'Free Agency'
    ) {
        echo "<table align=right bgcolor=#ff0000>
                <tr>
                    <td align=center><a href=\"modules.php?name=Player&pa=negotiate&pid=$playerID\">RENEGOTIATE<BR>CONTRACT</a></td>
                </tr>
            </table>";
    }

    // RENEGOTIATION BUTTON END

    if (
        $userTeam->name != "Free Agents"
        AND $player->canRookieOption($season->phase)
        AND $player->teamName == $userTeam->name
        ) {
            echo "<table align=right bgcolor=#ffbb00>
                <tr>
                    <td align=center><a href=\"modules.php?name=Player&pa=rookieoption&pid=$playerID\">ROOKIE<BR>OPTION</a></td>
                </tr>
            </table>";
    }

    $contract_display = implode("/", $player->getRemainingContractArray());

    echo "<font class=\"content\">Age: $player->age | Height: $player->heightFeet-$player->heightInches | Weight: $player->weightPounds | College: $player->collegeName<br>
        <i>Drafted by the $player->draftTeamOriginalName with the # $player->draftPickNumber pick of round $player->draftRound in the <a href=\"draft.php?year=$player->draftYear\">$player->draftYear Draft</a></i><br>
        <center><table>
            <tr>
                <td align=center><b>2ga</b></td>
                <td align=center><b>2gp</b></td>
                <td align=center><b>fta</b></td>
                <td align=center><b>ftp</b></td>
                <td align=center><b>3ga</b></td>
                <td align=center><b>3gp</b></td>
                <td align=center><b>orb</b></td>
                <td align=center><b>drb</b></td>
                <td align=center><b>ast</b></td>
                <td align=center><b>stl</b></td>
                <td align=center><b>tvr</b></td>
                <td align=center><b>blk</b></td>
                <td align=center><b>foul</b></td>
                <td align=center><b>oo</b></td>
                <td align=center><b>do</b></td>
                <td align=center><b>po</b></td>
                <td align=center><b>to</b></td>
                <td align=center><b>od</b></td>
                <td align=center><b>dd</b></td>
                <td align=center><b>pd</b></td>
                <td align=center><b>td</b></td>
            </tr>
            <tr>
                <td align=center>$player->ratingFieldGoalAttempts</td>
                <td align=center>$player->ratingFieldGoalPercentage</td>
                <td align=center>$player->ratingFreeThrowAttempts</td>
                <td align=center>$player->ratingFreeThrowPercentage</td>
                <td align=center>$player->ratingThreePointAttempts</td>
                <td align=center>$player->ratingThreePointPercentage</td>
                <td align=center>$player->ratingOffensiveRebounds</td>
                <td align=center>$player->ratingDefensiveRebounds</td>
                <td align=center>$player->ratingAssists</td>
                <td align=center>$player->ratingSteals</td>
                <td align=center>$player->ratingTurnovers</td>
                <td align=center>$player->ratingBlocks</td>
                <td align=center>$player->ratingFouls</td>
                <td align=center>$player->ratingOutsideOffense</td>
                <td align=center>$player->ratingDriveOffense</td>
                <td align=center>$player->ratingPostOffense</td>
                <td align=center>$player->ratingTransitionOffense</td>
                <td align=center>$player->ratingOutsideDefense</td>
                <td align=center>$player->ratingDriveDefense</td>
                <td align=center>$player->ratingPostDefense</td>
                <td align=center>$player->ratingTransitionDefense</td>
            </tr>
        </table></center>
    <b>BIRD YEARS:</b> $player->birdYears | <b>Remaining Contract:</b> $contract_display </td>";

    if ($spec == null) {
        // ==== PLAYER SEASON AND CAREER HIGHS ====

        echo "<td rowspan=3 valign=top>

        <table border=1 cellspacing=0 cellpadding=0>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>PLAYER HIGHS</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>Regular-Season</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td></td>
                <td><font color=#ffffff>Ssn</font></td>
                <td><font color=#ffffff>Car</td>
            </tr>
            <tr>
                <td><b>Points</b></td>
                <td>$playerStats->seasonHighPoints</td>
                <td>$playerStats->careerSeasonHighPoints</td>
            </tr>
            <tr>
                <td><b>Rebounds</b></td>
                <td>$playerStats->seasonHighRebounds</td>
                <td>$playerStats->careerSeasonHighRebounds</td>
            </tr>
            <tr>
                <td><b>Assists</b></td>
                <td>$playerStats->seasonHighAssists</td>
                <td>$playerStats->careerSeasonHighAssists</td>
            </tr>
            <tr>
                <td><b>Steals</b></td>
                <td>$playerStats->seasonHighSteals</td>
                <td>$playerStats->careerSeasonHighSteals</td>
            </tr>
            <tr>
                <td><b>Blocks</b></td>
                <td>$playerStats->seasonHighBlocks</td>
                <td>$playerStats->careerSeasonHighBlocks</td>
            </tr>
            <tr>
                <td>Double-Doubles</td>
                <td>$playerStats->seasonDoubleDoubles</td>
                <td>$playerStats->careerDoubleDoubles</td>
            </tr>
            <tr>
                <td>Triple-Doubles</td>
                <td>$playerStats->seasonTripleDoubles</td>
                <td>$playerStats->careerTripleDoubles</td>
            </tr>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>Playoffs</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td></td>
                <td><font color=#ffffff>Ssn</font></td>
                <td><font color=#ffffff>Car</td>
            </tr>
            <tr>
                <td><b>Points</b></td>
                <td>$playerStats->seasonPlayoffHighPoints</td>
                <td>$playerStats->careerPlayoffHighPoints</td>
            </tr>
            <tr>
                <td><b>Rebounds</b></td>
                <td>$playerStats->seasonPlayoffHighRebounds</td>
                <td>$playerStats->careerPlayoffHighRebounds</td>
            </tr>
            <tr>
                <td><b>Assists</b></td>
                <td>$playerStats->seasonPlayoffHighAssists</td>
                <td>$playerStats->careerPlayoffHighAssists</td>
            </tr>
            <tr>
                <td><b>Steals</b></td>
                <td>$playerStats->seasonPlayoffHighSteals</td>
                <td>$playerStats->careerPlayoffHighSteals</td>
            </tr>
            <tr>
                <td><b>Blocks</b></td>
                <td>$playerStats->seasonPlayoffHighBlocks</td>
                <td>$playerStats->careerPlayoffHighBlocks</td>
            </tr>
        </table></td>";

        // ==== END PLAYER SEASON AND CAREER HIGHS ====

    }

    echo "<tr>
        <td colspan=2><hr></td>
    </tr>
    <tr>
        <td colspan=2><b><center>PLAYER MENU</center></b><br>
            <center>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID\">Player Overview</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=1\">Bio (Awards, News)</a><br>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=2\">One-on-one Results</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=10\">Season Sim Stats</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=0\">Game Log</font></a><br>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=3\">Regular-Season Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=4\">Regular-Season Averages</a><br>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=5\">Playoff Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=6\">Playoff Averages</a><br>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=7\">H.E.A.T. Totals</a> | <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=8\">H.E.A.T. Averages</a><br>
            <a href=\"modules.php?name=Player&pa=showpage&pid=$playerID&spec=9\">Ratings and Salary History</a>
            </center>
        </td>
    </tr>
    <tr>
        <td colspan=3><hr></td>
    </tr>";

    // PLAYER OVERVIEW

    if ($spec == null) {
        // NOTE ALL-STAR WEEKEND APPEARANCES

        echo "<tr>
            <td colspan=3>
                <table align=left cellspacing=1 cellpadding=0 border=1>
                    <th colspan=2><center>All-Star Activity</center></th>
        </tr>";

        $allstarquery = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' AND Award LIKE '%Conference All-Star'");
        $asg = $db->sql_numrows($allstarquery);
        echo "<tr>
            <td><b>All Star Games:</b></td>
            <td>$asg</td>
        </tr>";

        $allstarquery2 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' AND Award LIKE 'Three-Point Contest%'");
        $threepointcontests = $db->sql_numrows($allstarquery2);

        echo "<tr>
            <td><b>Three-Point<br>Contests:</b></td>
            <td>$threepointcontests</td>
        </tr>";

        $allstarquery3 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' AND Award LIKE 'Slam Dunk Competition%'");
        $dunkcontests = $db->sql_numrows($allstarquery3);

        echo "<tr>
            <td><b>Slam Dunk<br>Competitions:</b></td>
            <td>$dunkcontests</td>
        </tr>";

        $allstarquery4 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' AND Award LIKE 'Rookie-Sophomore Challenge'");
        $rooksoph = $db->sql_numrows($allstarquery4);

        echo "<tr>
            <td><b>Rookie-Sophomore<br>Challenges:</b></td>
            <td>$rooksoph</td>
        </tr>
        </table>";

        // END ALL-STAR WEEKEND ACTIVITY SCRIPT

        echo "<center>
        <table>
            <tr align=center>
                <td><b>Talent</b></td>
                <td><b>Skill</b></td>
                <td><b>Intangibles</b></td>
                <td><b>Clutch</b></td>
                <td><b>Consistency</b></td>
            </tr>
            <tr align=center>
                <td>$player->ratingTalent</td>
                <td>$player->ratingSkill</td>
                <td>$player->ratingIntangibles</td>
                <td>$player->ratingClutch</td>
                <td>$player->ratingConsistency</td>
            </tr>
        </table>
        <table>
            <tr>
                <td><b>Loyalty</b></td>
                <td><b>Play for Winner</b></td>
                <td><b>Playing Time</b></td>
                <td><b>Security</b></td>
                <td><b>Tradition</b></td>
            </tr>
            <tr align=center>
                <td>$player->freeAgencyLoyalty</td>
                <td>$player->freeAgencyPlayForWinner</td>
                <td>$player->freeAgencyPlayingTime</td>
                <td>$player->freeAgencySecurity</td>
                <td>$player->freeAgencyTradition</td>
            </tr>
        </table>
        </center>
        </td></tr></table>
        <table>";
    }

    echo "</table><table>";

    // SIM STATS

    if ($spec == 10) {
        echo "<table align=center border=1 cellpadding=3 cellspacing=0 style=\"text-align: center\">
            <tr>
                <td colspan=16><b><font class=\"content\">Sim Averages</font></b></td>
            </tr>
            <tr style=\"font-weight: bold\">
                <td>sim</td>
                <td>g</td>
                <td>min</td>
                <td>FGP</td>
                <td>FTP</td>
                <td>3GP</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $resultSimDates = $db->sql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim ASC");
        while ($simDates = $db->sql_fetchrow($resultSimDates)) {
            $simNumber = $simDates['Sim'];
            $simStartDate = $simDates['Start Date'];
            $simEndDate = $simDates['End Date'];

            $resultPlayerSimBoxScores = $db->sql_query("SELECT *
                FROM ibl_box_scores
                WHERE pid = $playerID
                AND Date BETWEEN '$simStartDate' AND '$simEndDate'
                ORDER BY Date ASC");

            $numberOfGamesPlayedInSim = $db->sql_numrows($resultPlayerSimBoxScores);
            $simTotalMIN = $simTotal2GM = $simTotal2GA = $simTotalFTM = $simTotalFTA = $simTotal3GM = $simTotal3GA = 0;
            $simTotalORB = $simTotalDRB = $simTotalAST = $simTotalSTL = $simTotalTOV = $simTotalBLK = $simTotalPF = $simTotalPTS = 0;

            while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
                $simTotalMIN += $row['gameMIN'];
                $simTotal2GM += $row['gameFGM'];
                $simTotal2GA += $row['gameFGA'];
                $simTotalFTM += $row['gameFTM'];
                $simTotalFTA += $row['gameFTA'];
                $simTotal3GM += $row['game3GM'];
                $simTotal3GA += $row['game3GA'];
                $simTotalORB += $row['gameORB'];
                $simTotalDRB += $row['gameDRB'];
                $simTotalAST += $row['gameAST'];
                $simTotalSTL += $row['gameSTL'];
                $simTotalTOV += $row['gameTOV'];
                $simTotalBLK += $row['gameBLK'];
                $simTotalPF += $row['gamePF'];
                $simTotalPTS += (2 * $row['gameFGM']) + $row['gameFTM'] + (3 * $row['game3GM']);
            }

            $simAverageMIN = ($numberOfGamesPlayedInSim) ? $simTotalMIN / $numberOfGamesPlayedInSim : "0.0";
            $simAverageFGP = ($simTotal2GA + $simTotal3GA) ? ($simTotal2GM + $simTotal3GM) / ($simTotal2GA + $simTotal3GA) : "0.000";
            $simAverageFTP = ($simTotalFTA) ? $simTotalFTM / $simTotalFTA : "0.000";
            $simAverage3GP = ($simTotal3GA) ? $simTotal3GM / $simTotal3GA : "0.000";
            $simAverageORB = ($numberOfGamesPlayedInSim) ? $simTotalORB / $numberOfGamesPlayedInSim : "0.0";
            $simAverageREB = ($numberOfGamesPlayedInSim) ? ($simTotalORB + $simTotalDRB) / $numberOfGamesPlayedInSim : "0.0";
            $simAverageAST = ($numberOfGamesPlayedInSim) ? $simTotalAST / $numberOfGamesPlayedInSim : "0.0";
            $simAverageSTL = ($numberOfGamesPlayedInSim) ? $simTotalSTL / $numberOfGamesPlayedInSim : "0.0";
            $simAverageTOV = ($numberOfGamesPlayedInSim) ? $simTotalTOV / $numberOfGamesPlayedInSim : "0.0";
            $simAverageBLK = ($numberOfGamesPlayedInSim) ? $simTotalBLK / $numberOfGamesPlayedInSim : "0.0";
            $simAveragePF = ($numberOfGamesPlayedInSim) ? $simTotalPF / $numberOfGamesPlayedInSim : "0.0";
            $simAveragePTS = ($numberOfGamesPlayedInSim) ? $simTotalPTS / $numberOfGamesPlayedInSim : "0.0";

            echo "<td>$simNumber</td>
            <td>$numberOfGamesPlayedInSim</td><td>";
            printf('%01.1f', $simAverageMIN);
            echo "</td><td>";
            printf('%01.3f', $simAverageFGP);
            echo "</td><td>";
            printf('%01.3f', $simAverageFTP);
            echo "</td><td>";
            printf('%01.3f', $simAverage3GP);
            echo "</td><td>";
            printf('%01.1f', $simAverageORB);
            echo "</td><td>";
            printf('%01.1f', $simAverageREB);
            echo "</td><td>";
            printf('%01.1f', $simAverageAST);
            echo "</td><td>";
            printf('%01.1f', $simAverageSTL);
            echo "</td><td>";
            printf('%01.1f', $simAverageTOV);
            echo "</td><td>";
            printf('%01.1f', $simAverageBLK);
            echo "</td><td>";
            printf('%01.1f', $simAveragePF);
            echo "</td><td>";
            printf('%01.1f', $simAveragePTS);
            echo "</td></tr>";

            // TODO: Add Season Averages to the bottom of this table for easy comparison between sim and season stats
        }

        echo "</table>";
    }

    // CAREER TOTALS

    if ($spec == 3) {
        // GET PAST STATS

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        echo "<tr>
            <td valign=top>
                <table border=1 cellspacing=0 class=\"sortable\>
                    <tr>
                        <td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Career Totals</b></font></center></td>
                    </tr>
                    <tr>
                        <td>year</td>
                        <td>team</td>
                        <td>g</td>
                        <td>min</td>
                        <td>FGM-FGA</td>
                        <td>FTM-FTA</td>
                        <td>3GM-3GA</td>
                        <td>orb</td>
                        <td>reb</td>
                        <td>ast</td>
                        <td>stl</td>
                        <td>to</td>
                        <td>blk</td>
                        <td>pf</td>
                        <td>pts</td>
                    </tr>";

        $result44 = $db->sql_query("SELECT * FROM ibl_hist WHERE pid=$playerID ORDER BY year ASC");

        while ($row44 = $db->sql_fetchrow($result44)) {
            $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
            $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
            $hist_tid = stripslashes(check_html($row44['teamid'], "nohtml"));
            $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
            $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
            $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
            $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<td><center>$hist_year</center></td>
                <td><center><a href=\"modules.php?name=Team&op=team&tid=$hist_tid&yr=$hist_year\">$hist_team</a></center></td>
                <td><center>$hist_gm</center></td>
                <td><center>$hist_min</center></td>
                <td><center>$hist_fgm-$hist_fga</center></td>
                <td><center>$hist_ftm-$hist_fta</center></td>
                <td><center>$hist_tgm-$hist_tga</center></td>
                <td><center>$hist_orb</center></td>
                <td><center>$hist_reb</center></td>
                <td><center>$hist_ast</center></td>
                <td><center>$hist_stl</center></td>
                <td><center>$hist_tvr</center></td>
                <td><center>$hist_blk</center></td>
                <td><center>$hist_pf</center></td>
                <td><center>$hist_pts</td>
            </tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;
        }

        // CURRENT YEAR TOTALS

        if ($player->isRetired == 0) {
            echo "<td><center>$year</center></td>
                <td><center>$player->teamName</center></td>
                <td><center>$playerStats->seasonGamesPlayed</center></td>
                <td><center>$playerStats->seasonMinutes</center></td>
                <td><center>$playerStats->seasonFieldGoalsMade-$playerStats->seasonFieldGoalsAttempted</center></td>
                <td><center>$playerStats->seasonFreeThrowsMade-$playerStats->seasonFreeThrowsAttempted</center></td>
                <td><center>$playerStats->seasonThreePointersMade-$playerStats->seasonThreePointersAttempted</center></td>
                <td><center>$playerStats->seasonOffensiveRebounds</center></td>
                <td><center>$playerStats->seasonTotalRebounds</center></td>
                <td><center>$playerStats->seasonAssists</center></td>
                <td><center>$playerStats->seasonSteals</center></td>
                <td><center>$playerStats->seasonTurnovers</center></td>
                <td><center>$playerStats->seasonBlocks</center></td>
                <td><center>$playerStats->seasonPersonalFouls</center></td>
                <td><center>$playerStats->seasonPoints</td>
            </tr>";

            $car_gm += $playerStats->seasonGamesPlayed;
            $car_min += $playerStats->seasonMinutes;
            $car_fgm += $playerStats->seasonFieldGoalsMade;
            $car_fga += $playerStats->seasonFieldGoalsAttempted;
            $car_ftm += $playerStats->seasonFreeThrowsMade;
            $car_fta += $playerStats->seasonFreeThrowsAttempted;
            $car_3gm += $playerStats->seasonThreePointersMade;
            $car_3ga += $playerStats->seasonThreePointersAttempted;
            $car_orb += $playerStats->seasonOffensiveRebounds;
            $car_reb += $playerStats->seasonTotalRebounds;
            $car_ast += $playerStats->seasonAssists;
            $car_stl += $playerStats->seasonSteals;
            $car_blk += $playerStats->seasonBlocks;
            $car_tvr += $playerStats->seasonTurnovers;
            $car_pf += $playerStats->seasonPersonalFouls;
            $car_pts += $playerStats->seasonPoints;
        }

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2 >Career Totals</td>
            <td><center>$car_gm</center></td>
            <td><center>$car_min</center></td>
            <td><center>$car_fgm-$car_fga</center></td>
            <td><center>$car_ftm-$car_fta</center></td>
            <td><center>$car_3gm-$car_3ga</center></td>
            <td><center>$car_orb</center></td>
            <td><center>$car_reb</center></td>
            <td><center>$car_ast</center></td>
            <td><center>$car_stl</center></td>
            <td><center>$car_tvr</center></td>
            <td><center>$car_blk</center></td>
            <td><center>$car_pf</center></td>
            <td><center>$car_pts</td>
        </tr></table>";
    }

    // CAREER TOTALS

    if ($spec == 4) {
        // SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><b><font class=\"content\">Career Averages</font></b></center></td>
            </tr>
            <tr>
                <th>year</th>
                <th>team</th>
                <th>g</th>
                <th>min</th>
                <th>fgm</th>
                <th>fga</th>
                <th>fgp</th>
                <th>ftm</th>
                <th>fta</th>
                <th>ftp</th>
                <th>3gm</th>
                <th>3ga</th>
                <th>3gp</th>
                <th>orb</th>
                <th>reb</th>
                <th>ast</th>
                <th>stl</th>
                <th>to</th>
                <th>blk</th>
                <th>pf</th>
                <th>pts</th>
            </tr>";

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        $result44 = $db->sql_query("SELECT * FROM ibl_hist WHERE pid=$playerID ORDER BY year ASC");
        while ($row44 = $db->sql_fetchrow($result44)) {
            $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
            $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
            $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
            $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
            $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : '0.000';
            $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            $hist_mpg = ($hist_gm) ? ($hist_min / $hist_gm) : "0.0";
            $hist_fgmpg = ($hist_gm) ? ($hist_fgm / $hist_gm) : "0.0";
            $hist_fgapg = ($hist_gm) ? ($hist_fga / $hist_gm) : "0.0";
            $hist_ftmpg = ($hist_gm) ? ($hist_ftm / $hist_gm) : "0.0";
            $hist_ftapg = ($hist_gm) ? ($hist_fta / $hist_gm) : "0.0";
            $hist_3gmpg = ($hist_gm) ? ($hist_tgm / $hist_gm) : "0.0";
            $hist_3gapg = ($hist_gm) ? ($hist_tga / $hist_gm) : "0.0";
            $hist_opg = ($hist_gm) ? ($hist_orb / $hist_gm) : "0.0";
            $hist_rpg = ($hist_gm) ? ($hist_reb / $hist_gm) : "0.0";
            $hist_apg = ($hist_gm) ? ($hist_ast / $hist_gm) : "0.0";
            $hist_spg = ($hist_gm) ? ($hist_stl / $hist_gm) : "0.0";
            $hist_tpg = ($hist_gm) ? ($hist_tvr / $hist_gm) : "0.0";
            $hist_bpg = ($hist_gm) ? ($hist_blk / $hist_gm) : "0.0";
            $hist_fpg = ($hist_gm) ? ($hist_pf / $hist_gm) : "0.0";
            $hist_ppg = ($hist_gm) ? ($hist_pts / $hist_gm) : "0.0";

            $car_gm += $hist_gm;
            $car_min += $hist_min;
            $car_fgm += $hist_fgm;
            $car_fga += $hist_fga;
            $car_ftm += $hist_ftm;
            $car_fta += $hist_fta;
            $car_3gm += $hist_tgm;
            $car_3ga += $hist_tga;
            $car_orb += $hist_orb;
            $car_reb += $hist_reb;
            $car_ast += $hist_ast;
            $car_stl += $hist_stl;
            $car_blk += $hist_blk;
            $car_tvr += $hist_tvr;
            $car_pf += $hist_pf;
            $car_pts += $hist_pts;

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>";
            printf('%01.1f', $hist_mpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fgmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fgapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_fgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ftmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ftapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_ftp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_3gmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_3gapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_tgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_opg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_rpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_apg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_spg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_tpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_bpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ppg);
            echo "</center></td></tr>";
        }

        // CURRENT YEAR AVERAGES

        if (!$player->isRetired) {
            echo "<tr align=center>
                <td><center>$year</center></td>
                <td><center>$player->teamName</center></td>
                <td><center>$playerStats->seasonGamesPlayed</center></td>
                <td><center>";
            printf('%01.1f', $playerStats->seasonMinutesPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonFieldGoalsMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonFieldGoalsAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $playerStats->seasonFieldGoalPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonFreeThrowsMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonFreeThrowsAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $playerStats->seasonFreeThrowPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonThreePointersMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonThreePointersAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $playerStats->seasonThreePointPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonOffensiveReboundsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonTotalReboundsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonAssistsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonStealsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonTurnoversPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonBlocksPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonPersonalFoulsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $playerStats->seasonPointsPerGame);
            echo "</center></td></tr>";

            $car_gm += $playerStats->seasonGamesPlayed;
            $car_min += $playerStats->seasonMinutes;
            $car_fgm += $playerStats->seasonFieldGoalsMade;
            $car_fga += $playerStats->seasonFieldGoalsAttempted;
            $car_ftm += $playerStats->seasonFreeThrowsMade;
            $car_fta += $playerStats->seasonFreeThrowsAttempted;
            $car_3gm += $playerStats->seasonThreePointersMade;
            $car_3ga += $playerStats->seasonThreePointersAttempted;
            $car_orb += $playerStats->seasonOffensiveRebounds;
            $car_reb += $playerStats->seasonTotalRebounds;
            $car_ast += $playerStats->seasonAssists;
            $car_stl += $playerStats->seasonSteals;
            $car_blk += $playerStats->seasonBlocks;
            $car_tvr += $playerStats->seasonTurnovers;
            $car_pf += $playerStats->seasonPersonalFouls;
            $car_pts += $playerStats->seasonPoints;
        }

        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_fgmpg = ($car_gm) ? $car_fgm / $car_gm : "0.0";
        $car_fgapg = ($car_gm) ? $car_fga / $car_gm : "0.0";
        $car_fgp = ($car_fga) ?$car_fgm / $car_fga : "0.000";
        $car_ftmpg = ($car_gm) ? $car_ftm / $car_gm : "0.0";
        $car_ftapg = ($car_gm) ? $car_fta / $car_gm : "0.0";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_3gmpg = ($car_gm) ? $car_3gm / $car_gm : "0.0";
        $car_3gapg = ($car_gm) ? $car_3ga / $car_gm : "0.0";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>Career Averages</td>
            <td><center>$car_gm</center></td>
            <td><center>";
        printf('%01.1f', $car_avgm);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_fgmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_fgapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_fgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_ftmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_ftapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_ftp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_3gmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_3gapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_tgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgo);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgr);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avga);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgs);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgt);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgb);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgf);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgp);
        echo "</center></td></tr>";

        // END PAST STATS GRAB
    }

    // CAREER PLAYOFF TOTALS

    if ($spec == 5) {
        // GET PAST PLAYOFF STATS

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Playoff Career Totals</b></font></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>team</td>
                <td>g</td>
                <td>min</td>
                <td>FGM-FGA</td>
                <td>FTM-FTA</td>
                <td>3GM-3GA</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $resultplayoff4 = $db->sql_query("SELECT * FROM ibl_playoff_stats WHERE name='$player->name' ORDER BY year ASC");
        while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {
            $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
            $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
            $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
            $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
            $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>$hist_min</center></td>
                <td><center>$hist_fgm-$hist_fga</center></td>
                <td><center>$hist_ftm-$hist_fta</center></td>
                <td><center>$hist_tgm-$hist_tga</center></td>
                <td><center>$hist_orb</center></td>
                <td><center>$hist_reb</center></td>
                <td><center>$hist_ast</center></td>
                <td><center>$hist_stl</center></td>
                <td><center>$hist_tvr</center></td>
                <td><center>$hist_blk</center></td>
                <td><center>$hist_pf</center></td>
                <td><center>$hist_pts</td>
            </tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;

        }

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>Playoff Totals</td>
            <td><center>$car_gm</center></td>
            <td><center>$car_min</center></td>
            <td><center>$car_fgm-$car_fga</center></td>
            <td><center>$car_ftm-$car_fta</center></td>
            <td><center>$car_3gm-$car_3ga</center></td>
            <td><center>$car_orb</center></td>
            <td><center>$car_reb</center></td>
            <td><center>$car_ast</center></td>
            <td><center>$car_stl</center></td>
            <td><center>$car_tvr</center></td>
            <td><center>$car_blk</center></td>
            <td><center>$car_pf</center></td>
            <td><center>$car_pts</td>
        </tr>";
    }

    // CAREER PLAYOFF AVERAGES

    if ($spec == 6) {
        // SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><b><font class=\"content\">Playoffs Career Averages</font></b></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>team</td>
                <td>g</td>
                <td>min</td>
                <td>FGP</td>
                <td>FTP</td>
                <td>3GP</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        $resultplayoff4 = $db->sql_query("SELECT * FROM ibl_playoff_stats WHERE name='$player->name' ORDER BY year ASC");
        while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {
            $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
            $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
            $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
            $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
            $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            $hist_mpg = ($hist_gm) ? ($hist_min / $hist_gm) : "0.0";
            $hist_opg = ($hist_gm) ? ($hist_orb / $hist_gm) : "0.0";
            $hist_rpg = ($hist_gm) ? ($hist_reb / $hist_gm) : "0.0";
            $hist_apg = ($hist_gm) ? ($hist_ast / $hist_gm) : "0.0";
            $hist_spg = ($hist_gm) ? ($hist_stl / $hist_gm) : "0.0";
            $hist_tpg = ($hist_gm) ? ($hist_tvr / $hist_gm) : "0.0";
            $hist_bpg = ($hist_gm) ? ($hist_blk / $hist_gm) : "0.0";
            $hist_fpg = ($hist_gm) ? ($hist_pf / $hist_gm) : "0.0";
            $hist_ppg = ($hist_gm) ? ($hist_pts / $hist_gm) : "0.0";

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>";
            printf('%01.1f', $hist_mpg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_fgp);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_ftp);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_tgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_opg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_rpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_apg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_spg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_tpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_bpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ppg);
            echo "</center></td></tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;

        }

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>Playoff Averages</td>
            <td><center>$car_gm</center></td>
            <td><center>";
        printf('%01.1f', $car_avgm);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_fgp);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_ftp);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_tgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgo);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgr);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avga);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgs);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgt);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgb);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgf);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgp);
        echo "</center></td></tr>";

        // END PAST PLAYOFF STATS GRAB
    }

    // CAREER H.E.A.T. TOTALS

    if ($spec == 7) {
        // GET PAST H.E.A.T. STATS

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><font class=\"content\" color=\"#000000\"><b>H.E.A.T. Career Totals</b></font></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>team</td>
                <td>g</td>
                <td>min</td>
                <td>FGM-FGA</td>
                <td>FTM-FTA</td>
                <td>3GM-3GA</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $resultplayoff4 = $db->sql_query("SELECT * FROM ibl_heat_stats WHERE name='$player->name' ORDER BY year ASC");
        while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {
            $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
            $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
            $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
            $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
            $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>$hist_min</center></td>
                <td><center>$hist_fgm-$hist_fga</center></td>
                <td><center>$hist_ftm-$hist_fta</center></td>
                <td><center>$hist_tgm-$hist_tga</center></td>
                <td><center>$hist_orb</center></td>
                <td><center>$hist_reb</center></td>
                <td><center>$hist_ast</center></td>
                <td><center>$hist_stl</center></td>
                <td><center>$hist_tvr</center></td>
                <td><center>$hist_blk</center></td>
                <td><center>$hist_pf</center></td>
                <td><center>$hist_pts</td>
            </tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;
        }

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>H.E.A.T. Totals</td>
            <td><center>$car_gm</center></td>
            <td><center>$car_min</center></td>
            <td><center>$car_fgm-$car_fga</center></td>
            <td><center>$car_ftm-$car_fta</center></td>
            <td><center>$car_3gm-$car_3ga</center></td>
            <td><center>$car_orb</center></td>
            <td><center>$car_reb</center></td>
            <td><center>$car_ast</center></td>
            <td><center>$car_stl</center></td>
            <td><center>$car_tvr</center></td>
            <td><center>$car_blk</center></td>
            <td><center>$car_pf</center></td>
            <td><center>$car_pts</td>
        </tr>";
    }

    // CAREER H.E.A.T. AVERAGES

    if ($spec == 8) {
        // SWITCH FROM CAREER TOTALS TO CAREER AVERAGES

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><b><font class=\"content\">H.E.A.T. Career Averages</font></b></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>team</td>
                <td>g</td>
                <td>min</td>
                <td>FGP</td>
                <td>FTP</td>
                <td>3GP</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        $resultplayoff4 = $db->sql_query("SELECT * FROM ibl_heat_stats WHERE name='$player->name' ORDER BY year ASC");
        while ($rowplayoff4 = $db->sql_fetchrow($resultplayoff4)) {
            $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
            $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
            $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
            $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
            $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            $hist_mpg = ($hist_gm) ? ($hist_min / $hist_gm) : "0.0";
            $hist_opg = ($hist_gm) ? ($hist_orb / $hist_gm) : "0.0";
            $hist_rpg = ($hist_gm) ? ($hist_reb / $hist_gm) : "0.0";
            $hist_apg = ($hist_gm) ? ($hist_ast / $hist_gm) : "0.0";
            $hist_spg = ($hist_gm) ? ($hist_stl / $hist_gm) : "0.0";
            $hist_tpg = ($hist_gm) ? ($hist_tvr / $hist_gm) : "0.0";
            $hist_bpg = ($hist_gm) ? ($hist_blk / $hist_gm) : "0.0";
            $hist_fpg = ($hist_gm) ? ($hist_pf / $hist_gm) : "0.0";
            $hist_ppg = ($hist_gm) ? ($hist_pts / $hist_gm) : "0.0";

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>";
            printf('%01.1f', $hist_mpg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_fgp);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_ftp);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_tgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_opg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_rpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_apg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_spg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_tpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_bpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ppg);
            echo "</center></td></tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;
        }

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr><td colspan=2>H.E.A.T. Averages</td>
            <td><center>$car_gm</center></td>
            <td><center>";
        printf('%01.1f', $car_avgm);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_fgp);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_ftp);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_tgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgo);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgr);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avga);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgs);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgt);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgb);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgf);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgp);
        echo "</center></td></tr>";

        // END PAST H.E.A.T. STATS GRAB
    }

    // PLAYER RATINGS

    if ($spec == 9) {
        // PLAYER RATINGS BY YEAR

        $rowcolor = 0;

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=24><center><b><font class=\"content\">(Past) Career Ratings by Year</font></b></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>2ga</td>
                <td>2gp</td>
                <td>fta</td>
                <td>ftp</td>
                <td>3ga</td>
                <td>3gp</td>
                <td>orb</td>
                <td>drb</td>
                <td>ast</td>
                <td>stl</td>
                <td>blk</td>
                <td>tvr</td>
                <td>oo</td>
                <td>do</td>
                <td>po</td>
                <td>to</td>
                <td>od</td>
                <td>dd</td>
                <td>pd</td>
                <td>td</td>
                <td>Off</td>
                <td>Def</td>
                <td>Salary</td>
            </tr>";

        $totalsalary = 0;

        $result44 = $db->sql_query("SELECT * FROM ibl_hist WHERE pid=$playerID ORDER BY year ASC");
        while ($row44 = $db->sql_fetchrow($result44)) {
            $r_year = stripslashes(check_html($row44['year'], "nohtml"));
            $r_2ga = stripslashes(check_html($row44['r_2ga'], "nohtml"));
            $r_2gp = stripslashes(check_html($row44['r_2gp'], "nohtml"));
            $r_fta = stripslashes(check_html($row44['r_fta'], "nohtml"));
            $r_ftp = stripslashes(check_html($row44['r_ftp'], "nohtml"));
            $r_3ga = stripslashes(check_html($row44['r_3ga'], "nohtml"));
            $r_3gp = stripslashes(check_html($row44['r_3gp'], "nohtml"));
            $r_orb = stripslashes(check_html($row44['r_orb'], "nohtml"));
            $r_drb = stripslashes(check_html($row44['r_drb'], "nohtml"));
            $r_ast = stripslashes(check_html($row44['r_ast'], "nohtml"));
            $r_stl = stripslashes(check_html($row44['r_stl'], "nohtml"));
            $r_blk = stripslashes(check_html($row44['r_blk'], "nohtml"));
            $r_tvr = stripslashes(check_html($row44['r_tvr'], "nohtml"));
            $r_oo = stripslashes(check_html($row44['r_oo'], "nohtml"));
            $r_do = stripslashes(check_html($row44['r_do'], "nohtml"));
            $r_po = stripslashes(check_html($row44['r_po'], "nohtml"));
            $r_to = stripslashes(check_html($row44['r_to'], "nohtml"));
            $r_od = stripslashes(check_html($row44['r_od'], "nohtml"));
            $r_dd = stripslashes(check_html($row44['r_dd'], "nohtml"));
            $r_pd = stripslashes(check_html($row44['r_pd'], "nohtml"));
            $r_td = stripslashes(check_html($row44['r_td'], "nohtml"));
            $salary = stripslashes(check_html($row44['salary'], "nohtml"));
            $r_Off = $r_oo + $r_do + $r_po + $r_to;
            $r_Def = $r_od + $r_dd + $r_pd + $r_td;

            $totalsalary = $totalsalary + $salary;

            echo "<td><center>$r_year</center></td>
                <td><center>$r_2ga</center></td>
                <td><center>$r_2gp</center></td>
                <td><center>$r_fta</center></td>
                <td><center>$r_ftp</center></td>
                <td><center>$r_3ga</center></td>
                <td><center>$r_3gp</center></td>
                <td><center>$r_orb</center></td>
                <td><center>$r_drb</center></td>
                <td><center>$r_ast</center></td>
                <td><center>$r_stl</center></td>
                <td><center>$r_blk</center></td>
                <td><center>$r_tvr</center></td>
                <td><center>$r_oo</center></td>
                <td><center>$r_do</center></td>
                <td><center>$r_po</center></td>
                <td><center>$r_to</center></td>
                <td><center>$r_od</center></td>
                <td><center>$r_dd</center></td>
                <td><center>$r_pd</center></td>
                <td><center>$r_td</center></td>
                <td><center>$r_Off</center></td>
                <td><center>$r_Def</center></td>
                <td><center>$salary</center></td>
            </tr>";
        }

        $totalsalary = $totalsalary / 100;

        echo "<tr>
            <td colspan=24><center><b>Total Career Salary Earned:</b> $totalsalary million dollars</td>
        </tr>";

        // END PLAYER RATINGS SECTION
    }

    // START AWARDS SCRIPT

    if ($spec == 1) {
        // START AWARDS SCRIPT

        $awardsquery = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' ORDER BY year ASC");

        echo "<table border=1 cellspacing=0 cellpadding=0 valign=top>
            <tr>
                <td bgcolor=#0000cc align=center><b><font color=#ffffff>AWARDS</font></b></td>
            </tr>";

        while ($awardsrow = $db->sql_fetchrow($awardsquery)) {
            $award_year = stripslashes(check_html($awardsrow['year'], "nohtml"));
            $award_type = stripslashes(check_html($awardsrow['Award'], "nohtml"));

            echo "<tr>
                <td>$award_year $award_type</td>
            </tr>";
        }

        // END AWARDS SCRIPT

        // START NEWS ARTICLE PICKUP

        echo "<tr>
            <td bgcolor=#0000cc align=center><b><font color=#ffffff>ARTICLES MENTIONING THIS PLAYER</font></b></td>
        </tr>
        <tr>
            <td>";

        $urlwanted = str_replace(" ", "%20", $player->name);

        readfile("http://iblhoops.net/ibl5/articles.php?player=$urlwanted"); // Relative URL paths don't seem to work for this

        // END NEWS ARTICLE PICKUP
    }

    if ($spec == 2) {
        // OPEN ONE-ON-ONE RESULTS

        echo "<tr>
            <td bgcolor=#0000cc align=center><b><font color=#ffffff>ONE-ON-ONE RESULTS</font></b></td>
        </tr>
        <tr>
            <td>";

        //$oneononeurlwanted=str_replace(" ", "%20", $player->name);

        //echo (readfile("online/1on1results.php?player=$oneononeurlwanted"));

        $player2 = str_replace("%20", " ", $player->name);

        $query = "SELECT * FROM ibl_one_on_one WHERE winner = '$player2' ORDER BY gameid ASC";
        $result = $db->sql_query($query);
        $num = $db->sql_numrows($result);

        $wins = 0;
        $losses = 0;

        $i = 0;

        while ($i < $num) {
            $gameid = $db->sql_result($result, $i, "gameid");
            $winner = $db->sql_result($result, $i, "winner");
            $loser = $db->sql_result($result, $i, "loser");
            $winscore = $db->sql_result($result, $i, "winscore");
            $lossscore = $db->sql_result($result, $i, "lossscore");

            echo "* def. $loser, $winscore-$lossscore (# $gameid)<br>";

            $wins++;
            $i++;
        }

        $query = "SELECT * FROM ibl_one_on_one WHERE loser = '$player2' ORDER BY gameid ASC";
        $result = $db->sql_query($query);
        $num = $db->sql_numrows($result);
        $i = 0;

        while ($i < $num) {
            $gameid = $db->sql_result($result, $i, "gameid");
            $winner = $db->sql_result($result, $i, "winner");
            $loser = $db->sql_result($result, $i, "loser");
            $winscore = $db->sql_result($result, $i, "winscore");
            $lossscore = $db->sql_result($result, $i, "lossscore");

            echo "* lost to $winner, $lossscore-$winscore (# $gameid)<br>";

            $losses++;
            $i++;
        }

        echo "<b><center>Record: $wins - $losses</center></b><br>";

        // END ONE-ON-ONE RESULTS
    }

    // GAME LOG

    if ($spec == 0) {
        if ($season->phase == "Preseason") {
            $query = "SELECT * FROM ibl_box_scores WHERE Date BETWEEN '$season->beginningYear-" . Season::IBL_PRESEASON_MONTH . "-01' AND '$season->endingYear-07-01' AND pid = $playerID ORDER BY Date ASC";
        } else {
            $query = "SELECT * FROM ibl_box_scores WHERE Date BETWEEN '$season->beginningYear-" . Season::IBL_HEAT_MONTH . "-01' AND '$season->endingYear-07-01' AND pid = $playerID ORDER BY Date ASC";
        }
        $result = $db->sql_query($query);

        echo '<p><H1><center>GAME LOG</center></H1><p><table class=\"sortable\" width="100%">
              <tr>
              <th>Date</th>
              <th>Away</th>
              <th>Home</th>
              <th>MIN</th>
              <th>PTS</th>
              <th>FGM</th>
              <th>FGA</th>
              <th>FG%</th>
              <th>FTM</th>
              <th>FTA</th>
              <th>FT%</th>
              <th>3GM</th>
              <th>3GA</th>
              <th>3G%</th>
              <th>ORB</th>
              <th>DRB</th>
              <th>REB</th>
              <th>AST</th>
              <th>STL</th>
              <th>TO</th>
              <th>BLK</th>
              <th>PF</th>
              </tr>
        ';

        while ($row = $db->sql_fetch_assoc($result)) {
            $fieldGoalPercentage = ($row['gameFGA'] + $row['game3GA']) ? number_format(($row['gameFGM'] + $row['game3GM']) / ($row['gameFGA'] + $row['game3GA']), 3, '.', '') : "0.000";
            $freeThrowPercentage = ($row['gameFTA']) ? number_format($row['gameFTM'] / $row['gameFTA'], 3, '.', '') : "0.000";
            $threePointPercentage = ($row['game3GA']) ? number_format($row['game3GM'] / $row['game3GA'], 3, '.', '') : "0.000";

            echo "<style>
                    td {}
                    .gamelog {text-align: center;}
                </style>
                <tr>
                    <td class=\"gamelog\">" . $row['Date'] . "</td>
                    <td class=\"gamelog\">" . $sharedFunctions->getTeamnameFromTid($row['visitorTID']) . "</td>
                    <td class=\"gamelog\">" . $sharedFunctions->getTeamnameFromTid($row['homeTID']) . "</td>
                    <td class=\"gamelog\">" . $row['gameMIN'] . "</td>
                    <td class=\"gamelog\">" . ((2 * $row['gameFGM']) + (3 * $row['game3GM']) + $row['gameFTM']) . "</td>
                    <td class=\"gamelog\">" . ($row['gameFGM'] + $row['game3GM']) . "</td>
                    <td class=\"gamelog\">" . ($row['gameFGA'] + $row['game3GA']) . "</td>
                    <td class=\"gamelog\">" . $fieldGoalPercentage . "</td>
                    <td class=\"gamelog\">" . $row['gameFTM'] . "</td>
                    <td class=\"gamelog\">" . $row['gameFTA'] . "</td>
                    <td class=\"gamelog\">" . $freeThrowPercentage . "</td>
                    <td class=\"gamelog\">" . $row['game3GM'] . "</td>
                    <td class=\"gamelog\">" . $row['game3GA'] . "</td>
                    <td class=\"gamelog\">" . $threePointPercentage . "</td>
                    <td class=\"gamelog\">" . $row['gameORB'] . "</td>
                    <td class=\"gamelog\">" . $row['gameDRB'] . "</td>
                    <td class=\"gamelog\">" . ($row['gameORB'] + $row['gameDRB']) . "</td>
                    <td class=\"gamelog\">" . $row['gameAST'] . "</td>
                    <td class=\"gamelog\">" . $row['gameSTL'] . "</td>
                    <td class=\"gamelog\">" . $row['gameTOV'] . "</td>
                    <td class=\"gamelog\">" . $row['gameBLK'] . "</td>
                    <td class=\"gamelog\">" . $row['gamePF'] . "</td>
                </tr>";
        }
    }

    echo "</td></tr></table></table>";

    CloseTable();
    include "footer.php";

    // END OF DISPLAY PAGE
}

function negotiate($pid)
{
    global $prefix, $db, $user, $cookie;

    $pid = intval($pid);
    $playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_plr WHERE pid = '$pid'"));
    $player_name = stripslashes(check_html($playerinfo['name'], "nohtml"));
    $player_pos = stripslashes(check_html($playerinfo['pos'], "nohtml"));
    $player_team_name = stripslashes(check_html($playerinfo['teamname'], "nohtml"));

    $player_loyalty = stripslashes(check_html($playerinfo['loyalty'], "nohtml"));
    $player_winner = stripslashes(check_html($playerinfo['winner'], "nohtml"));
    $player_playingtime = stripslashes(check_html($playerinfo['playingTime'], "nohtml"));
    $player_tradition = stripslashes(check_html($playerinfo['tradition'], "nohtml"));

    NukeHeader::header();
    OpenTable();
    UI::playerMenu();

    // RENEGOTIATION STUFF

    cookiedecode($user);

    $sql2 = "SELECT * FROM " . $prefix . "_users WHERE username = '$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);

    $userteam = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

    $player_exp = stripslashes(check_html($playerinfo['exp'], "nohtml"));
    $player_bird = stripslashes(check_html($playerinfo['bird'], "nohtml"));
    $yearOfCurrentContract = stripslashes(check_html($playerinfo['cy'], "nohtml"));
    $salaryIn2ndYearOfCurrentContract = stripslashes(check_html($playerinfo['cy2'], "nohtml"));
    $salaryIn3rdYearOfCurrentContract = stripslashes(check_html($playerinfo['cy3'], "nohtml"));
    $salaryIn4thYearOfCurrentContract = stripslashes(check_html($playerinfo['cy4'], "nohtml"));
    $salaryIn5thYearOfCurrentContract = stripslashes(check_html($playerinfo['cy5'], "nohtml"));
    $salaryIn6thYearOfCurrentContract = stripslashes(check_html($playerinfo['cy6'], "nohtml"));

    // CONTRACT CHECKER

    $can_renegotiate = 0;

    if (
        ($yearOfCurrentContract == 0 AND $salaryIn2ndYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 1 AND $salaryIn2ndYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 2 AND $salaryIn3rdYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 3 AND $salaryIn4thYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 4 AND $salaryIn5thYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 5 AND $salaryIn6thYearOfCurrentContract == 0)
        OR ($yearOfCurrentContract == 6)
    ) {
        $can_renegotiate = 1;
    }

    // END CONTRACT CHECKER

    echo "<b>$player_pos $player_name</b> - Contract Demands:
    <br>";

    if ($can_renegotiate == 1) {
        if ($player_team_name == $userteam) {
            // Assign player stats to variables
            $negotiatingPlayerFGA = intval($playerinfo['r_fga']);
            $negotiatingPlayerFGP = intval($playerinfo['r_fgp']);
            $negotiatingPlayerFTA = intval($playerinfo['r_fta']);
            $negotiatingPlayerFTP = intval($playerinfo['r_ftp']);
            $negotiatingPlayerTGA = intval($playerinfo['r_tga']);
            $negotiatingPlayerTGP = intval($playerinfo['r_tgp']);
            $negotiatingPlayerORB = intval($playerinfo['r_orb']);
            $negotiatingPlayerDRB = intval($playerinfo['r_drb']);
            $negotiatingPlayerAST = intval($playerinfo['r_ast']);
            $negotiatingPlayerSTL = intval($playerinfo['r_stl']);
            $negotiatingPlayerTOV = intval($playerinfo['r_to']);
            $negotiatingPlayerBLK = intval($playerinfo['r_blk']);
            $negotiatingPlayerFOUL = intval($playerinfo['r_foul']);
            $negotiatingPlayerOO = intval($playerinfo['oo']);
            $negotiatingPlayerOD = intval($playerinfo['od']);
            $negotiatingPlayerDO = intval($playerinfo['do']);
            $negotiatingPlayerDD = intval($playerinfo['dd']);
            $negotiatingPlayerPO = intval($playerinfo['po']);
            $negotiatingPlayerPD = intval($playerinfo['pd']);
            $negotiatingPlayerTO = intval($playerinfo['to']);
            $negotiatingPlayerTD = intval($playerinfo['td']);

            // Pull max values of each stat category
            $marketMaxFGA = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_fga`) FROM ibl_plr"));
            $marketMaxFGP = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_fgp`) FROM ibl_plr"));
            $marketMaxFTA = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_fta`) FROM ibl_plr"));
            $marketMaxFTP = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_ftp`) FROM ibl_plr"));
            $marketMaxTGA = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_tga`) FROM ibl_plr"));
            $marketMaxTGP = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_tgp`) FROM ibl_plr"));
            $marketMaxORB = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_orb`) FROM ibl_plr"));
            $marketMaxDRB = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_drb`) FROM ibl_plr"));
            $marketMaxAST = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_ast`) FROM ibl_plr"));
            $marketMaxSTL = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_stl`) FROM ibl_plr"));
            $marketMaxTOV = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_to`) FROM ibl_plr"));
            $marketMaxBLK = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_blk`) FROM ibl_plr"));
            $marketMaxFOUL = $db->sql_fetchrow($db->sql_query("SELECT MAX(`r_foul`) FROM ibl_plr"));
            $marketMaxOO = $db->sql_fetchrow($db->sql_query("SELECT MAX(`oo`) FROM ibl_plr"));
            $marketMaxOD = $db->sql_fetchrow($db->sql_query("SELECT MAX(`od`) FROM ibl_plr"));
            $marketMaxDO = $db->sql_fetchrow($db->sql_query("SELECT MAX(`do`) FROM ibl_plr"));
            $marketMaxDD = $db->sql_fetchrow($db->sql_query("SELECT MAX(`dd`) FROM ibl_plr"));
            $marketMaxPO = $db->sql_fetchrow($db->sql_query("SELECT MAX(`po`) FROM ibl_plr"));
            $marketMaxPD = $db->sql_fetchrow($db->sql_query("SELECT MAX(`pd`) FROM ibl_plr"));
            $marketMaxTO = $db->sql_fetchrow($db->sql_query("SELECT MAX(`to`) FROM ibl_plr"));
            $marketMaxTD = $db->sql_fetchrow($db->sql_query("SELECT MAX(`td`) FROM ibl_plr"));

            // Determine raw score for each stat
            $rawFGA = intval(round($negotiatingPlayerFGA / intval($marketMaxFGA[0]) * 100));
            $rawFGP = intval(round($negotiatingPlayerFGP / intval($marketMaxFGP[0]) * 100));
            $rawFTA = intval(round($negotiatingPlayerFTA / intval($marketMaxFTA[0]) * 100));
            $rawFTP = intval(round($negotiatingPlayerFTP / intval($marketMaxFTP[0]) * 100));
            $rawTGA = intval(round($negotiatingPlayerTGA / intval($marketMaxTGA[0]) * 100));
            $rawTGP = intval(round($negotiatingPlayerTGP / intval($marketMaxTGP[0]) * 100));
            $rawORB = intval(round($negotiatingPlayerORB / intval($marketMaxORB[0]) * 100));
            $rawDRB = intval(round($negotiatingPlayerDRB / intval($marketMaxDRB[0]) * 100));
            $rawAST = intval(round($negotiatingPlayerAST / intval($marketMaxAST[0]) * 100));
            $rawSTL = intval(round($negotiatingPlayerSTL / intval($marketMaxSTL[0]) * 100));
            $rawTOV = intval(round($negotiatingPlayerTOV / intval($marketMaxTOV[0]) * 100));
            $rawBLK = intval(round($negotiatingPlayerBLK / intval($marketMaxBLK[0]) * 100));
            $rawFOUL = intval(round($negotiatingPlayerFOUL / intval($marketMaxFOUL[0]) * 100));
            $rawOO = intval(round($negotiatingPlayerOO / intval($marketMaxOO[0]) * 100));
            $rawOD = intval(round($negotiatingPlayerOD / intval($marketMaxOD[0]) * 100));
            $rawDO = intval(round($negotiatingPlayerDO / intval($marketMaxDO[0]) * 100));
            $rawDD = intval(round($negotiatingPlayerDD / intval($marketMaxDD[0]) * 100));
            $rawPO = intval(round($negotiatingPlayerPO / intval($marketMaxPO[0]) * 100));
            $rawPD = intval(round($negotiatingPlayerPD / intval($marketMaxPD[0]) * 100));
            $rawTO = intval(round($negotiatingPlayerTO / intval($marketMaxTO[0]) * 100));
            $rawTD = intval(round($negotiatingPlayerTD / intval($marketMaxTD[0]) * 100));
            $totalRawScore = $rawFGA + $rawFGP + $rawFTA + $rawFTP + $rawTGA + $rawTGP + $rawORB + $rawDRB + $rawAST + $rawSTL + $rawTOV + $rawBLK + $rawFOUL +
                $rawOO + $rawOD + $rawDO + $rawDD + $rawPO + $rawPD + $rawTO + $rawTD;
            //    var_dump($totalRawScore);
            $adjustedScore = $totalRawScore - 700; // MJ's 87-88 season numbers = 1414 raw score! Sam Mack's was 702. So I cut the score down by 700.
            $demandsFactor = 3; // I got this number by trial-and-error until the first-round picks of the dispersal draft demanded around a max.
            $avgDemands = $adjustedScore * $demandsFactor;
            $totalDemands = $avgDemands * 5;
            $baseDemands = $totalDemands / 6;
            $maxRaise = round($baseDemands * 0.1);

            $dem1 = $baseDemands;
            $dem2 = $baseDemands + $maxRaise;
            $dem3 = $baseDemands + $maxRaise * 2;
            $dem4 = $baseDemands + $maxRaise * 3;
            $dem5 = $baseDemands + $maxRaise * 4;
            $dem6 = 0;
            /*
            // Old way to determine demands here
            $demands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_demands WHERE name='$player_name'"));
            $dem1 = stripslashes(check_html($demands['dem1'], "nohtml"));
            $dem2 = stripslashes(check_html($demands['dem2'], "nohtml"));
            $dem3 = stripslashes(check_html($demands['dem3'], "nohtml"));
            $dem4 = stripslashes(check_html($demands['dem4'], "nohtml"));
            $dem5 = stripslashes(check_html($demands['dem5'], "nohtml"));
            // The sixth year is zero for extensions only; remove the line below and uncomment the regular line in the FA module.
            $dem6 = 0;
            //    $dem6 = stripslashes(check_html($demands['dem6'], "nohtml"));
             */
            $teamfactors = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_team_info WHERE team_name = '$userteam'"));
            $tf_wins = stripslashes(check_html($teamfactors['Contract_Wins'], "nohtml"));
            $tf_loss = stripslashes(check_html($teamfactors['Contract_Losses'], "nohtml"));
            $tf_trdw = stripslashes(check_html($teamfactors['Contract_AvgW'], "nohtml"));
            $tf_trdl = stripslashes(check_html($teamfactors['Contract_AvgL'], "nohtml"));

            $millionsatposition = $db->sql_query("SELECT * FROM ibl_plr WHERE teamname = '$userteam' AND pos = '$player_pos' AND name != '$player_name'");
            // LOOP TO GET MILLIONS COMMITTED AT POSITION

            $tf_millions = 0;

            while ($millionscounter = $db->sql_fetchrow($millionsatposition)) {
                $millionscy = stripslashes(check_html($millionscounter['cy'], "nohtml"));
                $millionscy2 = stripslashes(check_html($millionscounter['cy2'], "nohtml"));
                $millionscy3 = stripslashes(check_html($millionscounter['cy3'], "nohtml"));
                $millionscy4 = stripslashes(check_html($millionscounter['cy4'], "nohtml"));
                $millionscy5 = stripslashes(check_html($millionscounter['cy5'], "nohtml"));
                $millionscy6 = stripslashes(check_html($millionscounter['cy6'], "nohtml"));

                // FOR AN EXTENSION, LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

                if ($millionscy == 1) {
                    $tf_millions = $tf_millions + $millionscy2;
                }
                if ($millionscy == 2) {
                    $tf_millions = $tf_millions + $millionscy3;
                }
                if ($millionscy == 3) {
                    $tf_millions = $tf_millions + $millionscy4;
                }
                if ($millionscy == 4) {
                    $tf_millions = $tf_millions + $millionscy5;
                }
                if ($millionscy == 5) {
                    $tf_millions = $tf_millions + $millionscy6;
                }
            }

            $demyrs = 6;
            if ($dem6 == 0) {
                $demyrs = 5;
                if ($dem5 == 0) {
                    $demyrs = 4;
                    if ($dem4 == 0) {
                        $demyrs = 3;
                        if ($dem3 == 0) {
                            $demyrs = 2;
                            if ($dem2 == 0) {
                                $demyrs = 1;
                            }
                        }
                    }
                }
            }

            //$modfactor1 = (0.0005*($tf_wins-$tf_losses)*($player_winner-1));
            $PFWFactor = (0.025 * ($tf_wins - $tf_loss) / ($tf_wins + $tf_loss) * ($player_winner - 1));
            //$modfactor2 = (0.00125*($tf_trdw-$tf_trdl)*($player_tradition-1));
            $traditionFactor = (0.025 * ($tf_trdw - $tf_trdl) / ($tf_trdw + $tf_trdl) * ($player_tradition - 1));
            //$modfactor3 = (.01*($tf_coach)*($player_coach=1));
            //$modfactor4 = (.025*($player_loyalty-1));
            $loyaltyFactor = (0.025 * ($player_loyalty - 1));
            $PTFactor = (($tf_millions * -0.00005) + 0.025) * ($player_playingtime - 1);

            $modifier = 1 + $PFWFactor + $traditionFactor + $loyaltyFactor + $PTFactor;
            //echo "Wins: $tf_wins<br>Loses: $tf_loss<br>Tradition Wins: $tf_trdw<br> Tradition Loses: $tf_trdl<br>Coach: $tf_coach<br>Loyalty: $player_loyalty<br>Play Time: $tf_millions<br>ModW: $modfactor1<br>ModT: $modfactor2<br>ModC: $modfactor3<br>ModL: $modfactor4<br>ModS: $modfactor5<br>ModP: $modfactor6<br>Mod: $modifier<br>Demand 1: $dem1<br>Demand 2: $dem2<br>Demand 3: $dem3<br>Demand 4: $dem4<br>Demand 5: $dem5<br>";
            
            $dem1 = round($dem1 / $modifier);
            $dem2 = round($dem2 / $modifier);
            $dem3 = round($dem3 / $modifier);
            $dem4 = round($dem4 / $modifier);
            $dem5 = round($dem5 / $modifier);
            // The sixth year is zero for extensions only; remove the line below and uncomment the regular line in the FA module.
            $dem6 = 0;
            // $dem6 = round($dem6/$modifier);

            $demtot = round(($dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6) / 100, 2);

            $demand_display = $dem1;
            if ($dem2 != 0) {
                $demand_display = $demand_display . "</td><td>" . $dem2;
            }
            if ($dem3 != 0) {
                $demand_display = $demand_display . "</td><td>" . $dem3;
            }
            if ($dem4 != 0) {
                $demand_display = $demand_display . "</td><td>" . $dem4;
            }
            if ($dem5 != 0) {
                $demand_display = $demand_display . "</td><td>" . $dem5;
            }
            if ($dem6 != 0) {
                $demand_display = $demand_display . "</td><td>" . $dem6;
            }

            // LOOP TO GET HARD CAP SPACE

            $capnumber = 7000;

            $capquery = "SELECT * FROM ibl_plr WHERE teamname='$userteam' AND retired = '0'";
            $capresult = $db->sql_query($capquery);
            while ($capdecrementer = $db->sql_fetchrow($capresult)) {

                $capcy = stripslashes(check_html($capdecrementer['cy'], "nohtml"));
                $capcy2 = stripslashes(check_html($capdecrementer['cy2'], "nohtml"));
                $capcy3 = stripslashes(check_html($capdecrementer['cy3'], "nohtml"));
                $capcy4 = stripslashes(check_html($capdecrementer['cy4'], "nohtml"));
                $capcy5 = stripslashes(check_html($capdecrementer['cy5'], "nohtml"));
                $capcy6 = stripslashes(check_html($capdecrementer['cy6'], "nohtml"));

                // LOOK AT SALARY COMMITTED NEXT YEAR, NOT THIS YEAR

                if ($capcy == 1) {
                    $capnumber = $capnumber - $capcy2;
                }
                if ($capcy == 2) {
                    $capnumber = $capnumber - $capcy3;
                }
                if ($capcy == 3) {
                    $capnumber = $capnumber - $capcy4;
                }
                if ($capcy == 4) {
                    $capnumber = $capnumber - $capcy5;
                }
                if ($capcy == 5) {
                    $capnumber = $capnumber - $capcy6;
                }
            }

            // ======= BEGIN HTML OUTPUT FOR RENEGOTIATION FUNCTION ======

            $fa_activecheck = $db->sql_fetchrow($db->sql_query("SELECT * FROM " . $prefix . "_modules WHERE title = 'Free_Agency'"));
            $fa_active = stripslashes(check_html($fa_activecheck['active'], "nohtml"));

            if ($fa_active == 1) {
                echo "Sorry, the contract extension feature is not available during free agency.";
            } else {
                echo "<form name=\"ExtensionOffer\" method=\"post\" action=\"extension.php\">";

                $maxyr1 = 1063;
                if ($player_exp > 6) {
                    $maxyr1 = 1275;
                }
                if ($player_exp > 9) {
                    $maxyr1 = 1451;
                }

                echo "Note that if you offer the max and I refuse, it means I am opting for Free Agency at the end of the season):
                    <table cellspacing=0 border=1>
                        <tr>
                            <td>My demands are:</td><td>$demand_display</td>
                        </tr>
                        <tr>
                            <td>Please enter your offer in this row:</td>
                        <td>";

                if ($dem1 < $maxyr1) {
                    echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$dem1\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$dem2\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$dem3\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$dem4\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$dem5\"></td>
                    </tr>";
                } else {
                    if ($player_bird >= 3) {
                        $maxraise = round($maxyr1 * 0.125);
                    } else {
                        $maxraise = round($maxyr1 * 0.1);
                    }

                    $maxyr2 = 0;
                    $maxyr3 = 0;
                    $maxyr4 = 0;
                    $maxyr5 = 0;

                    if ($dem2 != 0) {
                        $maxyr2 = $maxyr1 + $maxraise;
                    }
                    if ($dem3 != 0) {
                        $maxyr3 = $maxyr2 + $maxraise;
                    }
                    if ($dem4 != 0) {
                        $maxyr4 = $maxyr3 + $maxraise;
                    }
                    if ($dem5 != 0) {
                        $maxyr5 = $maxyr4 + $maxraise;
                    }

                    echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$maxyr1\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$maxyr2\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$maxyr3\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$maxyr4\"></td>
                        <td><INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$maxyr5\"></td>
                    </tr>";
                }

                echo "<tr>
                    <td colspan=6><b>Notes/Reminders:</b>
                        <ul>
                            <li>You have $capnumber in cap space available; the amount you offer in year 1 cannot exceed this.</li>
                            <li>Based on my years of service, the maximum amount you can offer me in year 1 is $maxyr1.</li>
                            <li>Enter \"0\" for years you do not want to offer a contract.</li>
                            <li>Contract extensions must be at least three years in length.</li>
                            <li>The amounts offered each year must equal or exceed the previous year.</li>";

                if ($player_bird >= 3) {
                    echo "<li>Because this player has Bird Rights, you may add no more than 12.5% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 75 between any two subsequent years.)</li>";
                } else {
                    echo "<li>Because this player does not have Bird Rights, you may add no more than 10% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>";
                }

                echo "<li>For reference, \"100\" entered in the fields above corresponds to 1 million dollars; the 50 million dollar soft cap thus means you have 5000 to play with. When re-signing your own players, you can go over the soft cap and up to the hard cap (7000).</li>
                    </ul></td></tr>
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"maxyr1\" value=\"$maxyr1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                </table>";

                echo "<input type=\"submit\" value=\"Offer Extension!\"></form>";
            }

        } else {
            echo "Sorry, this player is not on your team.";
        }
    } else {
        echo "Sorry, this player is not eligible for a contract extension at this time.";
    }

    // RENEGOTIATION STUFF END

    CloseTable();
    include "footer.php";
}

function rookieoption($pid)
{
    global $prefix, $db, $cookie;
    $sharedFunctions = new Shared($db);
    $season = new Season($db);
    $player = Player::withPlayerID($db, $pid);

    $userteam = $sharedFunctions->getTeamnameFromUsername($cookie[1]);

    if ($userteam != $player->teamName) {
        echo "$player->position $player->name is not on your team.<br>
            <a href=\"javascript:history.back()\">Go Back</a>";
        return;
    }

    if ($player->draftRound == 1 AND $player->canRookieOption($season->phase)) {
        $finalYearOfRookieContract = $player->contractYear3Salary;
    } elseif ($player->draftRound == 2 AND $player->canRookieOption($season->phase)) {
        $finalYearOfRookieContract = $player->contractYear2Salary;
    } else {
        echo "Sorry, $player->position $player->name is not eligible for a rookie option.<p>
            Only draft picks are eligible for rookie options, and the option must be exercised
            before the final season of their rookie contract is underway.<p>
    		<a href=\"javascript:history.back()\">Go Back</a>";
        return;
    }

    $rookieOptionValue = 2 * $finalYearOfRookieContract;

    echo "<img align=left src=\"images/player/$pid.jpg\">
    	You may exercise the rookie extension option on <b>$player->position $player->name</b>.<br>
    	Their contract value the season after this one will be <b>$rookieOptionValue</b>.<br>
    	However, by exercising this option, <b>you can't use an in-season contract extension on them next season</b>.<br>
    	<b>They will become a free agent</b>.<br>
    	<form name=\"RookieExtend\" method=\"post\" action=\"rookieoption.php\">
            <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
            <input type=\"hidden\" name=\"playerID\" value=\"$player->playerID\">
            <input type=\"hidden\" name=\"rookieOptionValue\" value=\"$rookieOptionValue\">
            <input type=\"submit\" value=\"Activate Rookie Extension\">
        </form>";
}

switch ($pa) {

    case "negotiate":
        negotiate($pid);
        break;

    case "rookieoption":
        rookieoption($pid);
        break;

    case "showpage":
        showpage($pid, $spec);
        break;
}
