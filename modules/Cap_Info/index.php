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

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

$currentSeasonEndingYear = mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Current Season Ending Year' LIMIT 1"), 0, "value");

include("header.php");

$queryTeamInfo = "SELECT * FROM nuke_ibl_team_info WHERE teamid != 35 ORDER BY teamid ASC";
$resultTeamInfo = mysql_query($queryTeamInfo);
$numberOfTeams = mysql_num_rows($resultTeamInfo);

OpenTable();

$i = 0;
while ($i < $numberOfTeams) {
	$teamid[$i] = mysql_result($resultTeamInfo, $i, "teamid");
	$teamname[$i] = mysql_result($resultTeamInfo, $i, "team_name");
	$teamcity[$i] = mysql_result($resultTeamInfo, $i, "team_city");
	$teamcolor1[$i] = mysql_result($resultTeamInfo, $i, "color1");
	$teamcolor2[$i] = mysql_result($resultTeamInfo, $i, "color2");

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

	$table_echo = $table_echo . "<tr>
		<td bgcolor=#" . $teamcolor1[$i] . ">
			<a href=\"modules.php?name=Team&op=team&tid=" . $teamid[$i] . "&display=contracts\">
				<font color=#" . $teamcolor2[$i] . ">" . $teamcity[$i] . " " . $teamname[$i] . "
			</a>
		</td>
		<td>" . $teamTotalSalaryYear1[$i] . "</td>
		<td>" . $teamTotalSalaryYear2[$i] . "</td>
		<td>" . $teamTotalSalaryYear3[$i] . "</td>
		<td>" . $teamTotalSalaryYear4[$i] . "</td>
		<td>" . $teamTotalSalaryYear5[$i] . "</td>
		<td>" . $teamTotalSalaryYear6[$i] . "</td>
		<td><center>" . $teamFreeAgencySlots[$i] . "</center></td>
	</tr>";

	$i++;
}

$text = $text . "<table class=\"sortable\" border=1>
	<tr>
		<th>Team</th>
		<th>" . ($currentSeasonEndingYear + 1) . "</th>
		<th>" . ($currentSeasonEndingYear + 2) . "</th>
		<th>" . ($currentSeasonEndingYear + 3) . "</th>
		<th>" . ($currentSeasonEndingYear + 4) . "</th>
		<th>" . ($currentSeasonEndingYear + 5) . "</th>
		<th>" . ($currentSeasonEndingYear + 6) . "</th>
		<th>FA Slots</th>
	</tr>
	$table_echo
</table>";
echo $text;

CloseTable();
include("footer.php");

function get_salary ($tid, $team_name, $currentSeasonEndingYear)
{
	// $querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
	// $resultpicks = mysql_query($querypicks);
	// $numpicks = mysql_num_rows($resultpicks);
	// $hh = 0;
	//
	// while ($hh < $numpicks)	{
	// 	$teampick = mysql_result($resultpicks, $hh, "teampick");
	// 	$year = mysql_result($resultpicks, $hh, "year");
	// 	$round = mysql_result($resultpicks, $hh, "round");
	// 	$j = $year - $currentSeasonEndingYear + 1;
	//
	// 	// if ($round == 1) {
	// 	// 	$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
	// 	// 	$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
	// 	// 	$j++;
	// 	// 	$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
	// 	// 	$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
	// 	// 	$j++;
	// 	// 	$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
	// 	// 	$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
	// 	// } else {
	// 	// 	$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
	// 	// 	$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
	// 	// 	$j++;
	// 	// 	$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
	// 	// 	$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
	// 	// }
	// 	$hh++;
	// }

	$queryPlayersUnderContractAfterThisSeason = "SELECT * FROM nuke_iblplyr WHERE retired = 0 AND tid = $tid AND cy <> cyt";
	$resultPlayersUnderContractAfterThisSeason = mysql_query($queryPlayersUnderContractAfterThisSeason);
	$numberOfPlayersUnderContractAfterThisSeason = mysql_num_rows($resultPlayersUnderContractAfterThisSeason);

	$i = 0;
	while ($i < $numberOfPlayersUnderContractAfterThisSeason) {
		$j = 1;
		$yearUnderContract = mysql_result($resultPlayersUnderContractAfterThisSeason, $i, "cy");
		$totalYearsUnderContract = mysql_result($resultPlayersUnderContractAfterThisSeason, $i, "cyt");

		while ($yearUnderContract < $totalYearsUnderContract) {
			$yearUnderContract = $yearUnderContract + 1;
			$contract_current_year[$yearUnderContract] = "cy" . $yearUnderContract;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"] + mysql_result($resultPlayersUnderContractAfterThisSeason, $i, $contract_current_year[$yearUnderContract]);
			$contract_amt[$j]["roster"]++;
			$j++;
		}
		$i++;
	}
	return $contract_amt;
}

function get_salary1 ($tid, $team_name, $currentSeasonEndingYear)
{
	$querypicks = "SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
	$resultpicks = mysql_query($querypicks);
	$numpicks = mysql_num_rows($resultpicks);
	$hh = 0;

	while ($hh < $numpicks) {
		$teampick = mysql_result($resultpicks, $hh, "teampick");
		$year = mysql_result($resultpicks, $hh, "year");
		$round = mysql_result($resultpicks, $hh, "round");
		$j = $year - $currentSeasonEndingYear + 1;
		if ($round == 1) {
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		} else {
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		}
		$hh++;
	}

	$queryPlayersUnderContractAfterThisSeason = "SELECT * FROM nuke_iblplyr WHERE retired = 0 AND tid = $tid AND droptime = 0 AND name NOT LIKE '%Buyout%' AND cy <> cyt";
	$resultPlayersUnderContractAfterThisSeason = mysql_query($queryPlayersUnderContractAfterThisSeason);
	$numberOfPlayersUnderContractAfterThisSeason = mysql_num_rows($resultPlayersUnderContractAfterThisSeason);

	$i = 0;
	while ($i < $numberOfPlayersUnderContractAfterThisSeason) {
		$j = 1;
		$yearUnderContract = mysql_result($resultPlayersUnderContractAfterThisSeason, $i, "cy");
		$totalYearsUnderContract = mysql_result($resultPlayersUnderContractAfterThisSeason, $i, "cyt");

		while ($yearUnderContract < $totalYearsUnderContract) {
			$yearUnderContract = $yearUnderContract + 1;
			$contract_current_year[$yearUnderContract] = "cy" . $yearUnderContract;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"] + mysql_result($resultPlayersUnderContractAfterThisSeason, $i, $contract_current_year[$yearUnderContract]);
			$contract_amt[$j]["roster"]++;
			$j++;
		}
		$i++;
	}
	return $contract_amt;
}

?>
