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

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$sharedFunctions = new Shared($db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

$currentSeasonEndingYear = $sharedFunctions->getCurrentSeasonEndingYear();

include "header.php";

$queryTeamInfo = "SELECT * FROM ibl_team_info WHERE teamid != 35 ORDER BY teamid ASC";
$resultTeamInfo = $db->sql_query($queryTeamInfo);
$numberOfTeams = $db->sql_numrows($resultTeamInfo);

OpenTable();

$i = 0;
while ($i < $numberOfTeams) {
    $teamid[$i] = $db->sql_result($resultTeamInfo, $i, "teamid");
    $teamname[$i] = $db->sql_result($resultTeamInfo, $i, "team_name");
    $teamcity[$i] = $db->sql_result($resultTeamInfo, $i, "team_city");
    $teamcolor1[$i] = $db->sql_result($resultTeamInfo, $i, "color1");
    $teamcolor2[$i] = $db->sql_result($resultTeamInfo, $i, "color2");

    $hasMLE[$i] = $db->sql_result($resultTeamInfo, $i, "HasMLE");
    $hasLLE[$i] = $db->sql_result($resultTeamInfo, $i, "HasLLE");
    $hasMLE[$i] = ($hasMLE[$i] == "1") ? "\u{2705}" : "\u{274C}";
    $hasLLE[$i] = ($hasLLE[$i] == "1") ? "\u{2705}" : "\u{274C}";

    $teamTotalSalaryYear1[$i] = 0;
    $teamTotalSalaryYear2[$i] = 0;
    $teamTotalSalaryYear3[$i] = 0;
    $teamTotalSalaryYear4[$i] = 0;
    $teamTotalSalaryYear5[$i] = 0;
    $teamTotalSalaryYear6[$i] = 0;
    $teamFreeAgencySlots[$i] = 15;

    $team_array = get_salary($teamid[$i], $teamname[$i], $currentSeasonEndingYear);
    $team_array1 = get_salary1($teamid[$i], $teamname[$i], $currentSeasonEndingYear);

    $teamTotalSalaryYear1[$i] = 7000 - $team_array[1]["salary"];
    $teamTotalSalaryYear2[$i] = 7000 - $team_array[2]["salary"];
    $teamTotalSalaryYear3[$i] = 7000 - $team_array[3]["salary"];
    $teamTotalSalaryYear4[$i] = 7000 - $team_array[4]["salary"];
    $teamTotalSalaryYear5[$i] = 7000 - $team_array[5]["salary"];
    $teamTotalSalaryYear6[$i] = 7000 - $team_array[6]["salary"];

    $teamFreeAgencySlots[$i] = $teamFreeAgencySlots[$i] - $team_array1[1]["roster"];

    $table_echo .= "<tr>
		<td bgcolor=#$teamcolor1[$i]>
			<a href=\"modules.php?name=Team&op=team&tid=$teamid[$i]&display=contracts\">
				<font color=#$teamcolor2[$i]>$teamcity[$i] $teamname[$i]
			</a>
		</td>
		<td align=center>$teamTotalSalaryYear1[$i]</td>
		<td align=center>$teamTotalSalaryYear2[$i]</td>
		<td align=center>$teamTotalSalaryYear3[$i]</td>
		<td align=center>$teamTotalSalaryYear4[$i]</td>
		<td align=center>$teamTotalSalaryYear5[$i]</td>
		<td align=center>$teamTotalSalaryYear6[$i]</td>
		<td align=center>$teamFreeAgencySlots[$i]</td>
        <td align=center>$hasMLE[$i]</td>
        <td align=center>$hasLLE[$i]</td>
	</tr>";

    $i++;
}

$text .= "<table class=\"sortable\" border=1>
	<tr>
		<th>Team</th>
		<th>" . ($currentSeasonEndingYear + 0) . "-<br>" . ($currentSeasonEndingYear + 1) . "</th>
		<th>" . ($currentSeasonEndingYear + 1) . "-<br>" . ($currentSeasonEndingYear + 2) . "</th>
		<th>" . ($currentSeasonEndingYear + 2) . "-<br>" . ($currentSeasonEndingYear + 3) . "</th>
		<th>" . ($currentSeasonEndingYear + 3) . "-<br>" . ($currentSeasonEndingYear + 4) . "</th>
		<th>" . ($currentSeasonEndingYear + 4) . "-<br>" . ($currentSeasonEndingYear + 5) . "</th>
		<th>" . ($currentSeasonEndingYear + 5) . "-<br>" . ($currentSeasonEndingYear + 6) . "</th>
		<th>FA Slots</th>
        <th>Has MLE</th>
        <th>Has LLE</th>
	</tr>
	$table_echo
</table>";
echo $text;

CloseTable();
include "footer.php";

function get_salary($tid, $team_name, $currentSeasonEndingYear)
{
    global $db;

    // $querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
    // $resultpicks = $db->sql_query($querypicks);
    // $numpicks = $db->sql_numrows($resultpicks);
    // $hh = 0;
    //
    // while ($hh < $numpicks)    {
    //     $teampick = $db->sql_result($resultpicks, $hh, "teampick");
    //     $year = $db->sql_result($resultpicks, $hh, "year");
    //     $round = $db->sql_result($resultpicks, $hh, "round");
    //     $j = $year - $currentSeasonEndingYear + 1;
    //
    //     if ($round == 1) {
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //     } else {
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //     }
    //     $hh++;
    // }

    $queryPlayersUnderContractAfterThisSeason = "SELECT * FROM ibl_plr WHERE retired = 0 AND tid = $tid AND cy <> cyt";
    $resultPlayersUnderContractAfterThisSeason = $db->sql_query($queryPlayersUnderContractAfterThisSeason);
    $numberOfPlayersUnderContractAfterThisSeason = $db->sql_numrows($resultPlayersUnderContractAfterThisSeason);

    $contract_amt[][] = 0;
    $i = 0;
    while ($i < $numberOfPlayersUnderContractAfterThisSeason) {
        $yearUnderContract = $db->sql_result($resultPlayersUnderContractAfterThisSeason, $i, "cy");
        $totalYearsUnderContract = $db->sql_result($resultPlayersUnderContractAfterThisSeason, $i, "cyt");

        $j = 1;
        while ($yearUnderContract < $totalYearsUnderContract) {
            $yearUnderContract = $yearUnderContract + 1;
            $contract_current_year[$yearUnderContract] = "cy" . $yearUnderContract;
            $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"] + $db->sql_result($resultPlayersUnderContractAfterThisSeason, $i, $contract_current_year[$yearUnderContract]);
            $contract_amt[$j]["roster"]++;
            $j++;
        }
        $i++;
    }
    return $contract_amt;
}

function get_salary1($tid, $team_name, $currentSeasonEndingYear)
{
    global $db;
    // $querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
    // $resultpicks = $db->sql_query($querypicks);
    // $numpicks = $db->sql_numrows($resultpicks);
    // $hh = 0;
    //
    // while ($hh < $numpicks) {
    //     $teampick = $db->sql_result($resultpicks, $hh, "teampick");
    //     $year = $db->sql_result($resultpicks, $hh, "year");
    //     $round = $db->sql_result($resultpicks, $hh, "round");
    //     $j = $year - $currentSeasonEndingYear + 1;
    //     if ($round == 1) {
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //     } else {
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //         $j++;
    //         $contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
    //         $contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
    //     }
    //     $hh++;
    // }

    $queryPlayersUnderContractAfterThisSeason = "SELECT * FROM ibl_plr WHERE retired = 0 AND tid = $tid AND cy <> cyt AND droptime = 0 AND name NOT LIKE '%Buyout%'";
    $resultPlayersUnderContractAfterThisSeason = $db->sql_query($queryPlayersUnderContractAfterThisSeason);
    $numberOfPlayersUnderContractAfterThisSeason = $db->sql_numrows($resultPlayersUnderContractAfterThisSeason);

    $contract_amt[][] = 0;
    $i = 0;
    while ($i < $numberOfPlayersUnderContractAfterThisSeason) {
        $yearUnderContract = $db->sql_result($resultPlayersUnderContractAfterThisSeason, $i, "cy");
        $totalYearsUnderContract = $db->sql_result($resultPlayersUnderContractAfterThisSeason, $i, "cyt");

        $j = 1;
        while ($yearUnderContract < $totalYearsUnderContract) {
            $yearUnderContract = $yearUnderContract + 1;
            $contract_amt[$j]["roster"]++;
            $j++;
        }
        $i++;
    }
    return $contract_amt;
}
