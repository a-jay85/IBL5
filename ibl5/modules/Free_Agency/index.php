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
/*                     IBL Free Agency Module                           */
/*               (c) July 22, 2005 by Spencer Cooley                    */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Free Agency System";

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
        display();
    }
}

function display()
{
    global $prefix, $db, $cookie;
    $sharedFunctions = new Shared($db);

    include "header.php";
    OpenTable();

    $sql2 = "SELECT * FROM " . $prefix . "_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);

    $tid = $sharedFunctions->getTidFromTeamname($userinfo['user_ibl_team']);
    $team = Team::withTeamID($db, $tid);

    $sharedFunctions->displaytopmenu($team->teamID);

    $currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

    $conttot1 = $conttot2 = $conttot3 = $conttot4 = $conttot5 = $conttot6 = 0;
    $rosterspots1 = $rosterspots2 = $rosterspots3 = $rosterspots4 = $rosterspots5 = $rosterspots6 = 15;

    echo "<center><img src=\"images/logo/$team->teamID.jpg\"></center><p>";

    // ==== DISPLAY PLAYERS CURRENTLY UNDER CONTRACT FOR TEAM

    echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$team->name Players Under Contract</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Options</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

    foreach ($team->getOrdinalActiveRosterResult() as $playerRow) {
        $player = Player::withPlrRow($db, $playerRow);

        $yearPlayerIsFreeAgent = $player->draftYear + $player->yearsOfExperience + $player->contractTotalYears - $player->contractCurrentYear;
        if ($yearPlayerIsFreeAgent != $currentSeasonEndingYear) {
            // === MATCH UP CONTRACT AMOUNTS WITH FUTURE YEARS BASED ON CURRENT YEAR OF CONTRACT

            $millionscy = $player->contractCurrentYear;
            $millionscy1 = $player->contractYear1Salary;
            $millionscy2 = $player->contractYear2Salary;
            $millionscy3 = $player->contractYear3Salary;
            $millionscy4 = $player->contractYear4Salary;
            $millionscy5 = $player->contractYear5Salary;
            $millionscy6 = $player->contractYear6Salary;

            $contract1 = $contract2 = $contract3 = $contract4 = $contract5 = $contract6 = 0;

            // if player name doesn't start with '|' (pipe symbol), then don't occupy a roster slot
            $firstCharacterOfPlayerName = substr($player->name, 0, 1); 

            if ($millionscy == 0) {
                $contract1 = $millionscy1;
                $contract2 = $millionscy2;
                $contract3 = $millionscy3;
                $contract4 = $millionscy4;
                $contract5 = $millionscy5;
                $contract6 = $millionscy6;

                if ($millionscy1 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
                if ($millionscy2 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots2--;
                }
                if ($millionscy3 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots3--;
                }
                if ($millionscy4 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots4--;
                }
                if ($millionscy5 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots5--;
                }
                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots6--;
                }
            }
            if ($millionscy == 1) {
                $contract1 = $millionscy2;
                $contract2 = $millionscy3;
                $contract3 = $millionscy4;
                $contract4 = $millionscy5;
                $contract5 = $millionscy6;

                if ($millionscy2 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
                if ($millionscy3 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots2--;
                }
                if ($millionscy4 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots3--;
                }
                if ($millionscy5 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots4--;
                }
                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots5--;
                }
            }
            if ($millionscy == 2) {
                $contract1 = $millionscy3;
                $contract2 = $millionscy4;
                $contract3 = $millionscy5;
                $contract4 = $millionscy6;

                if ($millionscy3 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
                if ($millionscy4 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots2--;
                }
                if ($millionscy5 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots3--;
                }
                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots4--;
                }
            }
            if ($millionscy == 3) {
                $contract1 = $millionscy4;
                $contract2 = $millionscy5;
                $contract3 = $millionscy6;

                if ($millionscy4 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
                if ($millionscy5 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots2--;
                }
                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots3--;
                }
            }
            if ($millionscy == 4) {
                $contract1 = $millionscy5;
                $contract2 = $millionscy6;

                if ($millionscy5 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots2--;
                }
            }
            if ($millionscy == 5) {
                $contract1 = $millionscy6;

                if ($millionscy6 != 0 AND $player->teamName == $team->name AND $firstCharacterOfPlayerName !== '|') {
                    $rosterspots1--;
                }
            }


            echo "<tr>
                <td>";

            // ==== ROOKIE OPTIONS
            if (
                ($player->draftRound == 1 && $player->yearsOfExperience == 2 && $millionscy4 == 0)
             OR ($player->draftRound == 2 && $player->yearsOfExperience == 1 && $millionscy3 == 0)
            ) {
                echo "<a href=\"modules.php?name=Player&pa=rookieoption&pid=$player->playerID\">Rookie Option</a>";
            }

            if ($player->ordinal > 960) {
                $player->name .= "*";
            }

            echo "</td>
                <td>$player->position</td>
                <td><a href=\"modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->name</a></td>
                <td><a href=\"modules.php?name=Team&op=team&tid=$player->teamID\">$player->teamName</a></td>
                <td>$player->age</td>
                <td>$player->ratingFieldGoalAttempts</td>
                <td>$player->ratingFieldGoalPercentage</td>
                <td>$player->ratingFreeThrowAttempts</td>
                <td>$player->ratingFreeThrowPercentage</td>
                <td>$player->ratingThreePointAttempts</td>
                <td>$player->ratingThreePointPercentage</td>
                <td>$player->ratingOffensiveRebounds</td>
                <td>$player->ratingDefensiveRebounds</td>
                <td>$player->ratingAssists</td>
                <td>$player->ratingSteals</td>
                <td>$player->ratingTurnovers</td>
                <td>$player->ratingBlocks</td>
                <td>$player->ratingFouls</td>
                <td>$player->ratingOutsideOffense</td>
                <td>$player->ratingDriveOffense</td>
                <td>$player->ratingPostOffense</td>
                <td>$player->ratingTransitionOffense</td>
                <td>$player->ratingOutsideDefense</td>
                <td>$player->ratingDriveDefense</td>
                <td>$player->ratingPostDefense</td>
                <td>$player->ratingTransitionDefense</td>
                <td>$player->ratingTalent</td>
                <td>$player->ratingSkill</td>
                <td>$player->ratingIntangibles</td>
                <td>$contract1</td>
                <td>$contract2</td>
                <td>$contract3</td>
                <td>$contract4</td>
                <td>$contract5</td>
                <td>$contract6</td>
                <td>$player->freeAgencyLoyalty</td>
                <td>$player->freeAgencyPlayForWinner</td>
                <td>$player->freeAgencyPlayingTime</td>
                <td>$player->freeAgencySecurity</td>
                <td>$player->freeAgencyTradition</td>
            </tr>";

            $conttot1 += $contract1;
            $conttot2 += $contract2;
            $conttot3 += $contract3;
            $conttot4 += $contract4;
            $conttot5 += $contract5;
            $conttot6 += $contract6;
        }
    }

    $showteam = $db->sql_query("SELECT * FROM ibl_plr WHERE (tid=$tid AND retired='0') ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {
        if ($yearoffreeagency != $currentSeasonEndingYear) {

            
        }
    }

    echo "</tbody>
		<tfoot>
			<tr>
				<td colspan=29 align=right><b><i>$team->name Total Committed Contracts</i></b></td>
				<td><b><i>$conttot1</i></b></td>
				<td><b><i>$conttot2</i></b></td>
				<td><b><i>$conttot3</i></b></td>
				<td><b><i>$conttot4</i></b></td>
				<td><b><i>$conttot5</i></b></td>
				<td><b><i>$conttot6</i></b></td>
			</tr>
		</tfoot>
	</table>

	<p>";

    // ==== END LIST OF PLAYERS CURRENTLY UNDER CONTRACT

    // ==== INSERT LIST OF PLAYERS WITH OFFERS

    echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$team->name Outstanding Contract Offers</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

    $showteam = $db->sql_query("SELECT * FROM ibl_plr WHERE retired='0' ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {
        $name = $teamlist['name'];

        $numoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ibl_fa_offers WHERE name='$name' AND team='$team->name'"));
        if ($numoffers == 1) {
            $team = $teamlist['teamname'];
            $tid = $teamlist['tid'];
            $pid = $teamlist['pid'];
            $pos = $teamlist['pos'];
            $age = $teamlist['age'];

            $getoffers = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_fa_offers WHERE name='$name' AND team='$team->name'"));

            $offer1 = $getoffers['offer1'];
            $offer2 = $getoffers['offer2'];
            $offer3 = $getoffers['offer3'];
            $offer4 = $getoffers['offer4'];
            $offer5 = $getoffers['offer5'];
            $offer6 = $getoffers['offer6'];

            $r_2ga = $teamlist['r_fga'];
            $r_2gp = $teamlist['r_fgp'];
            $r_fta = $teamlist['r_fta'];
            $r_ftp = $teamlist['r_ftp'];
            $r_3ga = $teamlist['r_tga'];
            $r_3gp = $teamlist['r_tgp'];
            $r_orb = $teamlist['r_orb'];
            $r_drb = $teamlist['r_drb'];
            $r_ast = $teamlist['r_ast'];
            $r_stl = $teamlist['r_stl'];
            $r_blk = $teamlist['r_blk'];
            $r_tvr = $teamlist['r_to'];
            $r_foul = $teamlist['r_foul'];
            $r_oo = $teamlist['oo'];
            $r_do = $teamlist['do'];
            $r_po = $teamlist['po'];
            $r_to = $teamlist['to'];
            $r_od = $teamlist['od'];
            $r_dd = $teamlist['dd'];
            $r_pd = $teamlist['pd'];
            $r_td = $teamlist['td'];

            $talent = $teamlist['talent'];
            $skill = $teamlist['skill'];
            $intangibles = $teamlist['intangibles'];

            $loy = $teamlist['loyalty'];
            $pfw = $teamlist['winner'];
            $pt = $teamlist['playingTime'];
            $sec = $teamlist['security'];
            $trad = $teamlist['tradition'];

            echo "<tr>
				<td><a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a></td>
				<td>$pos</td>
				<td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$offer1</td>
				<td>$offer2</td>
				<td>$offer3</td>
				<td>$offer4</td>
				<td>$offer5</td>
				<td>$offer6</td>
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";

            $conttot1 += $offer1;
            $conttot2 += $offer2;
            $conttot3 += $offer3;
            $conttot4 += $offer4;
            $conttot5 += $offer5;
            $conttot6 += $offer6;

            if ($offer1 != 0) {
                $rosterspots1--;
            }
            if ($offer2 != 0) {
                $rosterspots2--;
            }
            if ($offer3 != 0) {
                $rosterspots3--;
            }
            if ($offer4 != 0) {
                $rosterspots4--;
            }
            if ($offer5 != 0) {
                $rosterspots5--;
            }
            if ($offer6 != 0) {
                $rosterspots6--;
            }
        }
    }

    echo "</tbody>
		<tfoot>
			<tr>
				<td colspan=29 align=right><b><i>$team->name Total Committed Plus Offered Contracts</i></b></td>
				<td><b><i>$conttot1</i></b></td>
				<td><b><i>$conttot2</i></b></td>
				<td><b><i>$conttot3</i></b></td>
				<td><b><i>$conttot4</i></b></td>
				<td><b><i>$conttot5</i></b></td>
				<td><b><i>$conttot6</i></b></td>
			</tr>";

    // ==== END INSERT OF PLAYERS WITH OFFERS

    $softcap = 5000 - $conttot1;
    $hardcap = 7000 - $conttot1;
    $softcap2 = 5000 - $conttot2;
    $hardcap2 = 7000 - $conttot2;
    $softcap3 = 5000 - $conttot3;
    $hardcap3 = 7000 - $conttot3;
    $softcap4 = 5000 - $conttot4;
    $hardcap4 = 7000 - $conttot4;
    $softcap5 = 5000 - $conttot5;
    $hardcap5 = 7000 - $conttot5;
    $softcap6 = 5000 - $conttot6;
    $hardcap6 = 7000 - $conttot6;

    // ===== CAP AND ROSTER SLOT INFO =====

    $exceptioninfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_team_info WHERE team_name='$team->name'"));

    $HasMLE = $exceptioninfo['HasMLE'];
    $HasLLE = $exceptioninfo['HasLLE'];

    echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Soft Cap Space</b></font></td>
		<td>$softcap</td>
		<td>$softcap2</td>
		<td>$softcap3</td>
		<td>$softcap4</td>
		<td>$softcap5</td>
		<td>$softcap6</td>
	</tr>";
    echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Hard Cap Space</b></font></td>
		<td>$hardcap</td>
		<td>$hardcap2</td>
		<td>$hardcap3</td>
		<td>$hardcap4</td>
		<td>$hardcap5</td>
		<td>$hardcap6</td>
	</tr>";
    echo "<tr bgcolor=#cc0000>
		<td colspan=21 bgcolor=#eeeeee></td>
		<td colspan=8 align=right><font color=white><b>Empty Roster Slots</b></font></td>
		<td>$rosterspots1</td>
		<td>$rosterspots2</td>
		<td>$rosterspots3</td>
		<td>$rosterspots4</td>
		<td>$rosterspots5</td>
		<td>$rosterspots6</td>
	</tr>";

    echo "<tr bgcolor=#cc0000><td colspan=35><font color=white><b>";

    if ($HasMLE == 1) {
        echo "Your team has access to the Mid-Level Exception (MLE) and hasn't signed a player with it (but you may have offered it to someone above).</b></font></td></tr>";
    } else {
        echo "Your team does NOT have access to the Mid-Level Exception - you either used it or didn't have sufficient cap space at the start of free agency.</b></font></td></tr>";
    }

    echo "                <tr bgcolor=#cc0000><td colspan=35><font color=white><b>";

    if ($HasLLE == 1) {
        echo "Your team has access to the Lower-Level Exception (LLE) and hasn't signed a player with it (but you may have offered it to someone above).</b></font></td></tr>";
    } else {
        echo "Your team does not have access to the Lower-Level Exception; you have already used it to sign a free agent.</b></font></td></tr>";
    }

    echo "</tfoot>
	</table>

	<p>
	<hr>
	<p>";

    // ==== INSERT LIST OF FREE AGENTS FROM TEAM

    echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>$team->name Unsigned Free Agents</b><br>
			(Note: * and <i>italicized</i> indicates player has Bird Rights)</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

    $showteam = $db->sql_query("SELECT * FROM ibl_plr WHERE teamname='$team->name' AND retired='0' ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {
        $draftyear = $teamlist['draftyear'];
        $exp = $teamlist['exp'];
        $cy = $teamlist['cy'];
        $cyt = $teamlist['cyt'];
        $yearPlayerIsFreeAgent = $draftyear + $exp + $cyt - $cy;

        if ($yearPlayerIsFreeAgent == $currentSeasonEndingYear) {
            $name = $teamlist['name'];
            $team = $teamlist['teamname'];
            $tid = $teamlist['tid'];
            $pid = $teamlist['pid'];
            $pos = $teamlist['pos'];
            $age = $teamlist['age'];
            $bird = $teamlist['bird'];

            $getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_demands WHERE name='$name'"));

            $dem1 = $getdemands['dem1'];
            $dem2 = $getdemands['dem2'];
            $dem3 = $getdemands['dem3'];
            $dem4 = $getdemands['dem4'];
            $dem5 = $getdemands['dem5'];
            $dem6 = $getdemands['dem6'];

            $r_2ga = $teamlist['r_fga'];
            $r_2gp = $teamlist['r_fgp'];
            $r_fta = $teamlist['r_fta'];
            $r_ftp = $teamlist['r_ftp'];
            $r_3ga = $teamlist['r_tga'];
            $r_3gp = $teamlist['r_tgp'];
            $r_orb = $teamlist['r_orb'];
            $r_drb = $teamlist['r_drb'];
            $r_ast = $teamlist['r_ast'];
            $r_stl = $teamlist['r_stl'];
            $r_blk = $teamlist['r_blk'];
            $r_tvr = $teamlist['r_to'];
            $r_foul = $teamlist['r_foul'];
            $r_oo = $teamlist['oo'];
            $r_do = $teamlist['do'];
            $r_po = $teamlist['po'];
            $r_to = $teamlist['to'];
            $r_od = $teamlist['od'];
            $r_dd = $teamlist['dd'];
            $r_pd = $teamlist['pd'];
            $r_td = $teamlist['td'];

            $talent = $teamlist['talent'];
            $skill = $teamlist['skill'];
            $intangibles = $teamlist['intangibles'];

            $loy = $teamlist['loyalty'];
            $pfw = $teamlist['winner'];
            $pt = $teamlist['playingTime'];
            $sec = $teamlist['security'];
            $trad = $teamlist['tradition'];

            echo "<tr>
				<td>";

            if ($rosterspots1 > 0) {
                echo "<a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a>";
            }

            echo "</td><td>$pos</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">";

            // ==== NOTE PLAYERS ON TEAM WITH BIRD RIGHTS

            if ($bird > 2) {
                echo "*<i>";
            }

            echo "$name";
            if ($bird > 2) {
                echo "</i>*";
            }

            // ==== END NOTE BIRD RIGHTS

            echo "</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$dem1</td>
				<td>$dem2</td>
				<td>$dem3</td>
				<td>$dem4</td>
				<td>$dem5</td>
				<td>$dem6</td>
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";
        }
    }

    echo "</table>

	<p>";

    // ==== END INSERT OF LIST OF FREE AGENTS FROM TEAM

    // ==== INSERT ALL OTHER FREE AGENTS

    echo "<table border=1 cellspacing=0 class=\"sortable\">
		<caption style=\"background-color: #0000cc\">
			<center><b><font color=white>All Other Free Agents</font></b></center>
		</caption>
		<colgroup>
			<col span=5>
			<col span=6 style=\"background-color: #ddd\">
			<col span=7>
			<col span=8 style=\"background-color: #ddd\">
			<col span=3>
			<col span=6 style=\"background-color: #ddd\">
			<col span=5>
		</colgroup>
		<thead>
			<tr>
				<td><b>Negotiate</b></td>
				<td><b>Pos</b></td>
				<td><b>Player</b></td>
				<td><b>Team</b></td>
				<td><b>Age</b></td>
				<td><b>2ga</b></td>
				<td><b>2g%</b></td>
				<td><b>fta</b></td>
				<td><b>ft%</b></td>
				<td><b>3ga</b></td>
				<td><b>3g%</b></td>
				<td><b>orb</b></td>
				<td><b>drb</b></td>
				<td><b>ast</b></td>
				<td><b>stl</b></td>
				<td><b>to</b></td>
				<td><b>blk</b></td>
				<td><b>foul</b></td>
				<td><b>oo</b></td>
				<td><b>do</b></td>
				<td><b>po</b></td>
				<td><b>to</b></td>
				<td><b>od</b></td>
				<td><b>dd</b></td>
				<td><b>pd</b></td>
				<td><b>td</b></td>
				<td><b>T</b></td>
				<td><b>S</b></td>
				<td><b>I</b></td>
				<td><b>Yr1</b></td>
				<td><b>Yr2</b></td>
				<td><b>Yr3</b></td>
				<td><b>Yr4</b></td>
				<td><b>Yr5</b></td>
				<td><b>Yr6</b></td>
				<td><b>Loy</b></td>
				<td><b>PFW</b></td>
				<td><b>PT</b></td>
				<td><b>Sec</b></td>
				<td><b>Trad</b></td>
			</tr>
		</thead>
		<tbody>";

    $showteam = $db->sql_query("SELECT * FROM ibl_plr WHERE teamname!='$team->name' AND retired='0' ORDER BY ordinal ASC");
    while ($teamlist = $db->sql_fetchrow($showteam)) {
        $draftyear = $teamlist['draftyear'];
        $exp = $teamlist['exp'];
        $cy = $teamlist['cy'];
        $cyt = $teamlist['cyt'];
        $yearPlayerIsFreeAgent = $draftyear + $exp + $cyt - $cy;

        if ($yearPlayerIsFreeAgent == $currentSeasonEndingYear) {
            $name = $teamlist['name'];
            $team = $teamlist['teamname'];
            $tid = $teamlist['tid'];
            $pid = $teamlist['pid'];
            $pos = $teamlist['pos'];
            $age = $teamlist['age'];

            $getdemands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_demands WHERE name='$name'"));

            $dem1 = $getdemands['dem1'];
            $dem2 = $getdemands['dem2'];
            $dem3 = $getdemands['dem3'];
            $dem4 = $getdemands['dem4'];
            $dem5 = $getdemands['dem5'];
            $dem6 = $getdemands['dem6'];

            $r_2ga = $teamlist['r_fga'];
            $r_2gp = $teamlist['r_fgp'];
            $r_fta = $teamlist['r_fta'];
            $r_ftp = $teamlist['r_ftp'];
            $r_3ga = $teamlist['r_tga'];
            $r_3gp = $teamlist['r_tgp'];
            $r_orb = $teamlist['r_orb'];
            $r_drb = $teamlist['r_drb'];
            $r_ast = $teamlist['r_ast'];
            $r_stl = $teamlist['r_stl'];
            $r_blk = $teamlist['r_blk'];
            $r_tvr = $teamlist['r_to'];
            $r_foul = $teamlist['r_foul'];
            $r_oo = $teamlist['oo'];
            $r_do = $teamlist['do'];
            $r_po = $teamlist['po'];
            $r_to = $teamlist['to'];
            $r_od = $teamlist['od'];
            $r_dd = $teamlist['dd'];
            $r_pd = $teamlist['pd'];
            $r_td = $teamlist['td'];

            $talent = $teamlist['talent'];
            $skill = $teamlist['skill'];
            $intangibles = $teamlist['intangibles'];

            $loy = $teamlist['loyalty'];
            $pfw = $teamlist['winner'];
            $pt = $teamlist['playingTime'];
            $sec = $teamlist['security'];
            $trad = $teamlist['tradition'];

            echo "<tr>
				<td>";

            if ($rosterspots1 > 0) {
                echo "<a href=\"modules.php?name=Free_Agency&pa=negotiate&pid=$pid\">Negotiate</a>";
            }

            echo "</td>
				<td>$pos</td>
				<td><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$tid\">$team</a></td>
				<td>$age</td>
				<td>$r_2ga</td>
				<td>$r_2gp</td>
				<td>$r_fta</td>
				<td>$r_ftp</td>
				<td>$r_3ga</td>
				<td>$r_3gp</td>
				<td>$r_orb</td>
				<td>$r_drb</td>
				<td>$r_ast</td>
				<td>$r_stl</td>
				<td>$r_tvr</td>
				<td>$r_blk</td>
				<td>$r_foul</td>
				<td>$r_oo</td>
				<td>$r_do</td>
				<td>$r_po</td>
				<td>$r_to</td>
				<td>$r_od</td>
				<td>$r_dd</td>
				<td>$r_pd</td>
				<td>$r_td</td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>";

            if ($exp > 0) {
                echo "<td>$dem1</td>
				<td>$dem2</td>
				<td>$dem3</td>
				<td>$dem4</td>
				<td>$dem5</td>
				<td>$dem6</td>";
            } else {
                // Limit undrafted rookie FA contracts to two years by only displaying their demands for years 3 and 4
                // this is hacky and assumes that the demands table always contains demands for years 3 and 4 instead of recalculating demands appropriately
                echo "<td>$dem3</td>
				<td>$dem4</td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>";
            }

            echo "
				<td>$loy</td>
				<td>$pfw</td>
				<td>$pt</td>
				<td>$sec</td>
				<td>$trad</td>
			</tr>";
        }
    }

    // ==== END INSERT OF ALL OTHER FREE AGENTS

    echo "</table>";

    CloseTable();
    include "footer.php";
}

// === START NEGOTIATE FUNCTION ===
function negotiate($pid)
{
    global $prefix, $db, $user, $cookie;
    $sharedFunctions = new Shared($db);

    $pid = intval($pid);

    cookiedecode($user);

    $sql2 = "SELECT * FROM " . $prefix . "_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);

    $userteam = $userinfo['user_ibl_team'];
    $tid = $sharedFunctions->getTidFromTeamname($userteam);

    $exceptioninfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_team_info WHERE team_name='$userteam'"));

    $HasMLE = $exceptioninfo['HasMLE'];
    $HasLLE = $exceptioninfo['HasLLE'];

    $playerinfo = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_plr WHERE pid='$pid'"));

    $player_name = $playerinfo['name'];
    $player_pos = $playerinfo['pos'];
    $player_team_name = $playerinfo['teamname'];

    include "header.php";
    OpenTable();

    $player_exp = $playerinfo['exp'];
    $player_bird = $playerinfo['bird'];

    $offer1 = 0;
    $offer2 = 0;
    $offer3 = 0;
    $offer4 = 0;
    $offer5 = 0;
    $offer6 = 0;

    echo "<b>$player_pos $player_name</b> - Contract Demands:
	<br>";

    $demands = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_demands WHERE name='$player_name'"));
    $dem1 = $demands['dem1'];
    $dem2 = $demands['dem2'];
    $dem3 = $demands['dem3'];
    $dem4 = $demands['dem4'];
    $dem5 = $demands['dem5'];
    $dem6 = $demands['dem6'];

    $millionsatposition = $db->sql_query("SELECT * FROM ibl_plr WHERE teamname='$userteam' AND pos='$player_pos' AND name!='$player_name'");

    // LOOP TO GET MILLIONS COMMITTED AT POSITION

    $tf_millions = 0;

    while ($millionscounter = $db->sql_fetchrow($millionsatposition)) {
        $millionscy = $millionscounter['cy'];
        $millionscy1 = $millionscounter['cy1'];
        $millionscy2 = $millionscounter['cy2'];
        $millionscy3 = $millionscounter['cy3'];
        $millionscy4 = $millionscounter['cy4'];
        $millionscy5 = $millionscounter['cy5'];
        $millionscy6 = $millionscounter['cy6'];

        // LOOK AT SALARY COMMITTED IN PROPER YEAR

        if ($millionscy == 0) {
            $tf_millions += $millionscy1;
        }

        if ($millionscy == 1) {
            $tf_millions += $millionscy2;
        }

        if ($millionscy == 2) {
            $tf_millions += $millionscy3;
        }

        if ($millionscy == 3) {
            $tf_millions += $millionscy4;
        }

        if ($millionscy == 4) {
            $tf_millions += $millionscy5;
        }

        if ($millionscy == 5) {
            $tf_millions += $millionscy6;
        }

    }

    // END LOOP

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

    $demtot = round(($dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6) / 100, 2);

    if ($player_exp > 0) {
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

        $demand_display .= "</td><td></td>";
    } else {
        // Limit undrafted rookie FA contracts to two years by only displaying their demands for years 3 and 4
        // this is hacky and assumes that the demands table always contains demands for years 3 and 4 instead of recalculating demands appropriately
        $demand_display = $dem3;
        if ($dem4 != 0) {
            $demand_display = $demand_display . "</td><td>" . $dem4;
        }

        $demand_display .= "</td><td></td>";
    }

    // LOOP TO GET SOFT CAP SPACE

    $capnumber = 5000;
    $capnumber2 = 5000;
    $capnumber3 = 5000;
    $capnumber4 = 5000;
    $capnumber5 = 5000;
    $capnumber6 = 5000;

    $rosterspots = 15;

    $capquery = "SELECT * FROM ibl_plr WHERE (tid=$tid AND retired='0') ORDER BY ordinal ASC;";
    $capresult = $db->sql_query($capquery);

    while ($capdecrementer = $db->sql_fetchrow($capresult)) {
        $ordinal = $capdecrementer['ordinal'];
        $capcy = $capdecrementer['cy'];
        $capcyt = $capdecrementer['cyt'];
        $capcy1 = $capdecrementer['cy1'];
        $capcy2 = $capdecrementer['cy2'];
        $capcy3 = $capdecrementer['cy3'];
        $capcy4 = $capdecrementer['cy4'];
        $capcy5 = $capdecrementer['cy5'];
        $capcy6 = $capdecrementer['cy6'];

        // LOOK AT SALARY COMMITTED IN PROPER YEAR

        if ($capcy == 0) {
            $capnumber -= $capcy1;
            $capnumber2 -= $capcy2;
            $capnumber3 -= $capcy3;
            $capnumber4 -= $capcy4;
            $capnumber5 -= $capcy5;
            $capnumber6 -= $capcy6;
        }
        if ($capcy == 1) {
            $capnumber -= $capcy2;
            $capnumber2 -= $capcy3;
            $capnumber3 -= $capcy4;
            $capnumber4 -= $capcy5;
            $capnumber5 -= $capcy6;
        }
        if ($capcy == 2) {
            $capnumber -= $capcy3;
            $capnumber2 -= $capcy4;
            $capnumber3 -= $capcy5;
            $capnumber4 -= $capcy6;
        }
        if ($capcy == 3) {
            $capnumber -= $capcy4;
            $capnumber2 -= $capcy5;
            $capnumber3 -= $capcy6;
        }
        if ($capcy == 4) {
            $capnumber -= $capcy5;
            $capnumber2 -= $capcy6;
        }
        if ($capcy == 5) {
            $capnumber -= $capcy6;
        }

        if ($capcy != $capcyt && $ordinal <= 960) {
            $rosterspots -= 1;
        }

    }

    $capquery2 = "SELECT * FROM ibl_fa_offers WHERE team='$userteam'";
    $capresult2 = $db->sql_query($capquery2);

    while ($capdecrementer2 = $db->sql_fetchrow($capresult2)) {
        $offer1 = $capdecrementer2['offer1'];
        $offer2 = $capdecrementer2['offer2'];
        $offer3 = $capdecrementer2['offer3'];
        $offer4 = $capdecrementer2['offer4'];
        $offer5 = $capdecrementer2['offer5'];
        $offer6 = $capdecrementer2['offer6'];
        $capnumber -= $offer1;
        $capnumber2 -= $offer2;
        $capnumber3 -= $offer3;
        $capnumber4 -= $offer4;
        $capnumber5 -= $offer5;
        $capnumber6 -= $offer6;
        $offer1 = 0;

        $rosterspots = $rosterspots - 1;
    }

    $hardcapnumber = $capnumber + 2000;
    $hardcapnumber2 = $capnumber2 + 2000;
    $hardcapnumber3 = $capnumber3 + 2000;
    $hardcapnumber4 = $capnumber4 + 2000;
    $hardcapnumber5 = $capnumber5 + 2000;
    $hardcapnumber6 = $capnumber6 + 2000;

    // END LOOP

    $offergrabber = $db->sql_fetchrow($db->sql_query("SELECT * FROM ibl_fa_offers WHERE team='$userteam' AND name='$player_name'"));

    $offer1 = $offergrabber['offer1'];
    $offer2 = $offergrabber['offer2'];
    $offer3 = $offergrabber['offer3'];
    $offer4 = $offergrabber['offer4'];
    $offer5 = $offergrabber['offer5'];
    $offer6 = $offergrabber['offer6'];

    if ($offer1 == 0) {
        $prefill1 = "";
        $prefill2 = "";
        $prefill3 = "";
        $prefill4 = "";
        $prefill5 = "";
        $prefill6 = "";
    } else {
        $prefill1 = $offer1;
        $prefill2 = $offer2;
        $prefill3 = $offer3;
        $prefill4 = $offer4;
        $prefill5 = $offer5;
        $prefill6 = $offer6;
    }

    if ($player_exp > 9) {
        $vetmin = 103;
        $maxstartsat = 1451;
    } elseif ($player_exp > 8) {
        $vetmin = 100;
        $maxstartsat = 1275;
    } elseif ($player_exp > 7) {
        $vetmin = 89;
        $maxstartsat = 1275;
    } elseif ($player_exp > 6) {
        $vetmin = 82;
        $maxstartsat = 1275;
    } elseif ($player_exp > 5) {
        $vetmin = 76;
        $maxstartsat = 1063;
    } elseif ($player_exp > 4) {
        $vetmin = 70;
        $maxstartsat = 1063;
    } elseif ($player_exp > 3) {
        $vetmin = 64;
        $maxstartsat = 1063;
    } elseif ($player_exp > 2) {
        $vetmin = 61;
        $maxstartsat = 1063;
    } elseif ($player_exp > 1) {
        $vetmin = 51;
        $maxstartsat = 1063;
    } else {
        $vetmin = 35;
        $maxstartsat = 1063;
    }

    // ==== CALCULATE MAX OFFER ====
    $Offer_max_increase = round($maxstartsat * 0.1, 0);
    $Offer_max_increase_bird = round($maxstartsat * 0.125, 0);

    $maxstartsat2 = $maxstartsat + $Offer_max_increase;
    $maxstartsat3 = $maxstartsat2 + $Offer_max_increase;
    $maxstartsat4 = $maxstartsat3 + $Offer_max_increase;
    $maxstartsat5 = $maxstartsat4 + $Offer_max_increase;
    $maxstartsat6 = $maxstartsat5 + $Offer_max_increase;

    $maxstartsatbird2 = $maxstartsat + $Offer_max_increase_bird;
    $maxstartsatbird3 = $maxstartsatbird2 + $Offer_max_increase_bird;
    $maxstartsatbird4 = $maxstartsatbird3 + $Offer_max_increase_bird;
    $maxstartsatbird5 = $maxstartsatbird4 + $Offer_max_increase_bird;
    $maxstartsatbird6 = $maxstartsatbird5 + $Offer_max_increase_bird;

    echo "<img align=left src=\"images/player/$pid.jpg\">";

    echo "Here are my demands (note these are not adjusted for your team's attributes; I will adjust the offer you make to me accordingly):";

    if ($rosterspots < 1 and $offer1 == 0) {
        echo "<table cellspacing=0 border=1><tr><td colspan=8>Sorry, you have no roster spots remaining and cannot offer me a contract!</td>";
    } else {
        echo "<table cellspacing=0 border=1><tr><td>My demands are:</td><td>$demand_display</td></tr>

		<form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
		<tr><td>Please enter your offer in this row:</td><td>";
        if ($player_exp > 0) {
            echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$prefill1\"></td><td>
                  <INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$prefill2\"></td><td>
                  <INPUT TYPE=\"text\" NAME=\"offeryear3\" SIZE=\"4\" VALUE=\"$prefill3\"></td><td>
                  <INPUT TYPE=\"text\" NAME=\"offeryear4\" SIZE=\"4\" VALUE=\"$prefill4\"></td><td>
                  <INPUT TYPE=\"text\" NAME=\"offeryear5\" SIZE=\"4\" VALUE=\"$prefill5\"></td><td>
                  <INPUT TYPE=\"text\" NAME=\"offeryear6\" SIZE=\"4\" VALUE=\"$prefill6\"></td>";
        } else { // Limit undrafted rookie FA contracts to two years
            echo "<INPUT TYPE=\"text\" NAME=\"offeryear1\" SIZE=\"4\" VALUE=\"$prefill3\"></td><td>
			      <INPUT TYPE=\"text\" NAME=\"offeryear2\" SIZE=\"4\" VALUE=\"$prefill4\"></td>";
        }
        $amendedCapSpaceYear1 = $capnumber + $offer1;
        echo "<input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
              <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
              <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
              <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
              <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
              <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
              <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
              <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
              <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
              <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
              <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
              <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
              <input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
              <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
              <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
              <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
              <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
              <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
              <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
              <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
              <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
              <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
	    <td>  <input type=\"submit\" value=\"Offer/Amend Free Agent Contract!\"></form></td></tr>

		<tr><td colspan=8><center><b>MAX SALARY OFFERS:</b></center></td></tr>

		<td>Max Level Contract 10%(click the button that corresponds to the final year you wish to offer):</td>

		<td>
            <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                <input type=\"submit\" value=\"$maxstartsat\">
            </form>
        </td>

		<td>
            <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
                <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                <input type=\"submit\" value=\"$maxstartsat2\">
            </form>
        </td>";

        if ($player_exp > 0) {
            echo "<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsat3\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsat4\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
                    <input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsat5\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsat5\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsat2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsat3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsat4\">
                    <input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsat5\">
                    <input type=\"hidden\" name=\"offeryear6\" value=\"$maxstartsat6\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                    <input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsat6\">
                </form>
            </td>";
        } else { // Limit undrafted rookie FA contracts to two years
            echo "";
        }

        echo "<td></td></tr>";

        // ===== CHECK TO SEE IF MAX BIRD RIGHTS IS AVAILABLE =====

        if ($player_bird > 2 && $player_team_name == $userteam) {
            echo "<tr><td><b>Max Bird Level Contract 12.5%(click the button that corresponds to the final year you wish to offer):</b></td>
			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsat\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsatbird2\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsatbird3\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsatbird4\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
                    <input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsatbird5\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsatbird5\">
                </form>
            </td>

			<td>
                <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                    <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                    <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                    <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                    <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                    <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                    <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                    <input type=\"hidden\" name=\"offeryear1\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"offeryear2\" value=\"$maxstartsatbird2\">
                    <input type=\"hidden\" name=\"offeryear3\" value=\"$maxstartsatbird3\">
                    <input type=\"hidden\" name=\"offeryear4\" value=\"$maxstartsatbird4\">
                    <input type=\"hidden\" name=\"offeryear5\" value=\"$maxstartsatbird5\">
                    <input type=\"hidden\" name=\"offeryear6\" value=\"$maxstartsatbird6\">
                    <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                    <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                    <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                    <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                    <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                    <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                    <input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
                    <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                    <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                    <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                    <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                    <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                    <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                    <input type=\"hidden\" name=\"bird\" value=\"$player_bird\">
                    <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                    <input type=\"hidden\" name=\"MLEyrs\" value=\"0\">
                    <input type=\"submit\" value=\"$maxstartsatbird6\">
                </form>
            </td>";
        }

        echo "<tr><td colspan=8><center><b>SALARY CAP EXCEPTIONS:</b></center></td></tr>";

        // ===== CHECK TO SEE IF MLE IS AVAILABLE =====

        if ($HasMLE == 1) {
            $MLEoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ibl_fa_offers WHERE MLE='1' AND team='$userteam'"));
            if ($MLEoffers == 0) {
                echo "<tr><td>Mid-Level Exception (click the button that corresponds to the final year you wish to offer):</td>

				<td>
                    <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                        <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                        <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                        <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                        <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                        <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                        <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                        <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                        <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                        <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                        <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                        <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                        <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                        <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                        <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                        <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                        <input type=\"hidden\" name=\"MLEyrs\" value=\"1\">
                        <input type=\"submit\" value=\"450\">
                    </form>
                </td>

				<td>
                    <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                        <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                        <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                        <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                        <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                        <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                        <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                        <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                        <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                        <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                        <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                        <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                        <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                        <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                        <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                        <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                        <input type=\"hidden\" name=\"MLEyrs\" value=\"2\">
                        <input type=\"submit\" value=\"495\">
                    </form>
                </td>";

                if ($player_exp > 0) {
                    echo "<td>
                        <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                            <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                            <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                            <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                            <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                            <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                            <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                            <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                            <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                            <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                            <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                            <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                            <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                            <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                            <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                            <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                            <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                            <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                            <input type=\"hidden\" name=\"MLEyrs\" value=\"3\">
                            <input type=\"submit\" value=\"540\">
                        </form>
                    </td>

					<td>
                        <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                            <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                            <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                            <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                            <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                            <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                            <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                            <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                            <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                            <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                            <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                            <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                            <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                            <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                            <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                            <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                            <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                            <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                            <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                            <input type=\"hidden\" name=\"MLEyrs\" value=\"4\">
                            <input type=\"submit\" value=\"585\">
                        </form>
                    </td>

					<td>
                        <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                            <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                            <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                            <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                            <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                            <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                            <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                            <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                            <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                            <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                            <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                            <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                            <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                            <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                            <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                            <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                            <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                            <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                            <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                            <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                            <input type=\"hidden\" name=\"MLEyrs\" value=\"5\">
                            <input type=\"submit\" value=\"630\">
                        </form>
                    </td>

					<td>
                        <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                            <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                            <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                            <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                            <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                            <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                            <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                            <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                            <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                            <input type=\"hidden\" name=\"capnumber2\" value=\"$capnumber2\">
                            <input type=\"hidden\" name=\"capnumber3\" value=\"$capnumber3\">
                            <input type=\"hidden\" name=\"capnumber4\" value=\"$capnumber4\">
                            <input type=\"hidden\" name=\"capnumber5\" value=\"$capnumber5\">
                            <input type=\"hidden\" name=\"capnumber6\" value=\"$capnumber6\">
                            <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                            <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                            <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                            <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                            <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                            <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                            <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                            <input type=\"hidden\" name=\"MLEyrs\" value=\"6\">
                            <input type=\"submit\" value=\"675\">
                        </form>
                    </td>";
                } else { // Limit undrafted rookie FA contracts to two years
                    echo "";
                }

                echo "<td></td></tr>";
            }
        }
        // ===== CHECK TO SEE IF LLE IS AVAILABLE =====

        if ($HasLLE == 1) {
            $LLEoffers = $db->sql_numrows($db->sql_query("SELECT * FROM ibl_fa_offers WHERE LLE='1' AND team='$userteam'"));
            if ($LLEoffers == 0) {
                echo "<tr><td>Lower-Level Exception:</td>
				<td>
                    <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                        <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                        <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                        <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                        <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                        <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                        <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                        <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                        <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                        <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                        <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                        <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                        <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                        <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                        <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                        <input type=\"hidden\" name=\"MLEyrs\" value=\"7\">
                        <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                        <input type=\"submit\" value=\"145\">
                    </form>
                </td>
				<td colspan=6></td></tr>";
            }
        }

        // ===== VETERANS EXCEPTION (ALWAYS AVAILABLE) =====

        echo "<tr><td>Veterans Exception:</td>
		<td>
            <form name=\"FAOffer\" method=\"post\" action=\"freeagentoffer.php\">
                <input type=\"hidden\" name=\"dem1\" value=\"$dem1\">
                <input type=\"hidden\" name=\"dem2\" value=\"$dem2\">
                <input type=\"hidden\" name=\"dem3\" value=\"$dem3\">
                <input type=\"hidden\" name=\"dem4\" value=\"$dem4\">
                <input type=\"hidden\" name=\"dem5\" value=\"$dem5\">
                <input type=\"hidden\" name=\"dem6\" value=\"$dem6\">
                <input type=\"hidden\" name=\"amendedCapSpaceYear1\" value=\"$amendedCapSpaceYear1\">
                <input type=\"hidden\" name=\"capnumber\" value=\"$capnumber\">
                <input type=\"hidden\" name=\"demtot\" value=\"$demtot\">
                <input type=\"hidden\" name=\"demyrs\" value=\"$demyrs\">
                <input type=\"hidden\" name=\"max\" value=\"$maxstartsat\">
                <input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
                <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
                <input type=\"hidden\" name=\"playername\" value=\"$player_name\">
                <input type=\"hidden\" name=\"MLEyrs\" value=\"8\">
                <input type=\"hidden\" name=\"vetmin\" value=\"$vetmin\">
                <input type=\"submit\" value=\"$vetmin\">
            </form>
        </td>
		<td colspan=6></td></tr>";
    }

    echo "
		<tr><td colspan=8><b>Notes/Reminders:</b> <ul>
		<li>The maximum contract permitted for me (based on my years of service) starts at $maxstartsat in Year 1.
		<li>You have <b>$amendedCapSpaceYear1</b> in <b>soft cap</b> space available; the amount you offer in year 1 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber2</b> in <b>soft cap</b> space available; the amount you offer in year 2 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber3</b> in <b>soft cap</b> space available; the amount you offer in year 3 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber4</b> in <b>soft cap</b> space available; the amount you offer in year 4 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber5</b> in <b>soft cap</b> space available; the amount you offer in year 5 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$capnumber6</b> in <b>soft cap</b> space available; the amount you offer in year 6 cannot exceed this unless you are using one of the exceptions.</li>
		<li>You have <b>$hardcapnumber</b> in <b>hard cap</b> space available; the amount you offer in year 1 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber2</b> in <b>hard cap</b> space available; the amount you offer in year 2 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber3</b> in <b>hard cap</b> space available; the amount you offer in year 3 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber4</b> in <b>hard cap</b> space available; the amount you offer in year 4 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber5</b> in <b>hard cap</b> space available; the amount you offer in year 5 cannot exceed this, period.</li>
		<li>You have <b>$hardcapnumber6</b> in <b>hard cap</b> space available; the amount you offer in year 6 cannot exceed this, period.</li>
		<li>Enter \"0\" for years you do not want to offer a contract.</li>
		<li>The amounts offered each year must equal or exceed the previous year.</li>
		<li>The first year of the contract must be at least the veteran's minimum ($vetmin for this player).</li>
		<li><b>For Players who do not have Bird Rights with your team:</b> You may add no more than 10% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>
		<li><b>Bird Rights Player on Your Team:</b> You may add no more than 12.5% of your the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 62 between any two subsequent years.)</li>
		<li>For reference, \"100\" entered in the fields above corresponds to 1 million dollars; the 50 million dollar soft cap thus means you have 5000 to play with.</li>
		</ul></td></tr>
		</table>

		</form>
	";

    echo "<form name=\"FAOfferDelete\" method=\"post\" action=\"freeagentofferdelete.php\">
		<input type=\"submit\" value=\"Retract All Offers to this Player!\">
		<input type=\"hidden\" name=\"teamname\" value=\"$userteam\">
        <input type=\"hidden\" name=\"player_teamname\" value=\"$player_team_name\">
		<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
		</form>";

    CloseTable();
    include "footer.php";
}
// === END OF NEGOTIATE FUNCTION ===

function teamdisplay($pid)
{
    global $prefix, $db, $user, $cookie;
    $sharedFunctions = new Shared($db);

    $pid = intval($pid);

    cookiedecode($user);
    include "header.php";
    OpenTable();

    echo "<center><h1>Cap Space and Roster Spots for all teams</h1></center>
		<table border=1 cellspacing=0><tr bgcolor=#0000cc><td><font color=#ffffff>Team</font></td><td><font color=#ffffff>Soft Cap Space</font></td><td><font color=#ffffff>Hard Cap Space</font></td><td><font color=#ffffff>Roster Slots</font></td><td><font color=#ffffff>MLE</font></td><td><font color=#ffffff>LLE</font></td></tr>";

    $showcapteam = $db->sql_query("SELECT * FROM ibl_team_info WHERE teamid>'0' ORDER BY teamid ASC");

    while ($teamcaplist = $db->sql_fetchrow($showcapteam)) {
        $currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

        $capteam = $teamcaplist['team_name'];
        $HasMLE = $teamcaplist['HasMLE'];
        $HasLLE = $teamcaplist['HasLLE'];
        $conttot1 = 0;
        $conttot2 = 0;
        $conttot3 = 0;
        $conttot4 = 0;
        $conttot5 = 0;
        $conttot6 = 0;
        $rosterspots = 15;

        // ==== NOTE PLAYERS CURRENTLY UNDER CONTRACT FOR TEAM

        $showteam = $db->sql_query("SELECT * FROM ibl_plr WHERE teamname='$capteam' AND retired='0' ORDER BY ordinal ASC");

        while ($teamlist = $db->sql_fetchrow($showteam)) {
            $ordinal = $teamlist['ordinal'];
            $draftyear = $teamlist['draftyear'];
            $exp = $teamlist['exp'];
            $cy = $teamlist['cy'];
            $cyt = $teamlist['cyt'];

            $yearoffreeagency = $draftyear + $exp + $cyt - $cy;

            if ($yearoffreeagency != $currentSeasonEndingYear) {
                // === MATCH UP CONTRACT AMOUNTS WITH FUTURE YEARS BASED ON CURRENT YEAR OF CONTRACT

                $millionscy = $teamlist['cy'];
                $millionscy1 = $teamlist['cy1'];
                $millionscy2 = $teamlist['cy2'];
                $millionscy3 = $teamlist['cy3'];
                $millionscy4 = $teamlist['cy4'];
                $millionscy5 = $teamlist['cy5'];
                $millionscy6 = $teamlist['cy6'];

                $contract1 = 0;
                $contract2 = 0;
                $contract3 = 0;
                $contract4 = 0;
                $contract5 = 0;
                $contract6 = 0;

                if ($millionscy == 0) {
                    $contract1 = $millionscy1;
                    $contract2 = $millionscy2;
                    $contract3 = $millionscy3;
                    $contract4 = $millionscy4;
                    $contract5 = $millionscy5;
                    $contract6 = $millionscy6;
                }
                if ($millionscy == 1) {
                    $contract1 = $millionscy2;
                    $contract2 = $millionscy3;
                    $contract3 = $millionscy4;
                    $contract4 = $millionscy5;
                    $contract5 = $millionscy6;
                }
                if ($millionscy == 2) {
                    $contract1 = $millionscy3;
                    $contract2 = $millionscy4;
                    $contract3 = $millionscy5;
                    $contract4 = $millionscy6;
                }
                if ($millionscy == 3) {
                    $contract1 = $millionscy4;
                    $contract2 = $millionscy5;
                    $contract3 = $millionscy6;
                }
                if ($millionscy == 4) {
                    $contract1 = $millionscy5;
                    $contract2 = $millionscy6;
                }
                if ($millionscy == 5) {
                    $contract1 = $millionscy6;
                }

                $conttot1 += $contract1;
                $conttot2 += $contract2;
                $conttot3 += $contract3;
                $conttot4 += $contract4;
                $conttot5 += $contract5;
                $conttot6 += $contract6;

                if ($ordinal <= 960) {
                    $rosterspots -= 1;
                }
            }
        }
        // ==== END LIST OF PLAYERS CURRENTLY UNDER CONTRACT

        $softcap = 5000 - $conttot1;
        $hardcap = 7000 - $conttot1;

        echo "<tr><td>$capteam</td><td>$softcap</td><td>$hardcap</td><td>$rosterspots</td>";

        echo "<td>" . ($HasMLE == 1 ? "MLE" : "") . "</td>";
        echo "<td>" . ($HasLLE == 1 ? "LLE" : "") . "</td>";

        echo "</tr>";
    }

    echo "</table>";

    CloseTable();
    include "footer.php";
}

switch ($pa) {
    case "display":
        display(1);
        break;

    case "teamdisplay":
        teamdisplay(1);
        break;

    case "negotiate":
        negotiate($pid);
        break;

    default:
        main($user);
        break;
}
