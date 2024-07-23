<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$sharedFunctions = new Shared($db);
$season = new Season($db);

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

NukeHeader::header();

$queryTeamInfo = "SELECT * FROM ibl_team_info WHERE teamid != " . League::FREE_AGENTS_TEAMID . " ORDER BY teamid ASC";
$resultTeamInfo = $db->sql_query($queryTeamInfo);
$numberOfTeams = $db->sql_numrows($resultTeamInfo);

OpenTable();

$i = 0;
while ($i < $numberOfTeams) {
    $teamRow = $db->sql_fetch_assoc($resultTeamInfo);
    $team = Team::withTeamRow($db, $teamRow);

    $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
    foreach ($positions as $position) {
        ${"team" . $position . "sResult"} = $team->getPlayersUnderContractByPositionResult($position);
        ${"teamTotal" . $position . "NextSeasonSalary"} = $team->getTotalNextSeasonSalariesFromPlrResult(${"team" . $position . "sResult"});
    }

    $teamTotalSalaryYear1[$i] = 0;
    $teamTotalSalaryYear2[$i] = 0;
    $teamTotalSalaryYear3[$i] = 0;
    $teamTotalSalaryYear4[$i] = 0;
    $teamTotalSalaryYear5[$i] = 0;
    $teamTotalSalaryYear6[$i] = 0;
    $teamFreeAgencySlots[$i] = 15;

    $team_array = get_salary($team->teamID);
    $team_array1 = get_salary1($team->teamID);

    $teamTotalSalaryYear1[$i] = 7000 - $team_array[1]["salary"];
    $teamTotalSalaryYear2[$i] = 7000 - $team_array[2]["salary"];
    $teamTotalSalaryYear3[$i] = 7000 - $team_array[3]["salary"];
    $teamTotalSalaryYear4[$i] = 7000 - $team_array[4]["salary"];
    $teamTotalSalaryYear5[$i] = 7000 - $team_array[5]["salary"];
    $teamTotalSalaryYear6[$i] = 7000 - $team_array[6]["salary"];

    $teamFreeAgencySlots[$i] = $teamFreeAgencySlots[$i] - $team_array1[1]["roster"];

    $MLEicon = ($team->hasMLE == "1") ? "\u{2705}" : "\u{274C}";
    $LLEicon = ($team->hasLLE == "1") ? "\u{2705}" : "\u{274C}";

    $table_echo .= "<tr>
		<td bgcolor=#$team->color1>
			<a href=\"modules.php?name=Team&op=team&tid=$team->teamID&display=contracts\">
				<font color=#$team->color2>$team->city $team->name
			</a>
		</td>
		<td align=center>$teamTotalSalaryYear1[$i]</td>
		<td align=center>$teamTotalSalaryYear2[$i]</td>
		<td align=center>$teamTotalSalaryYear3[$i]</td>
		<td align=center>$teamTotalSalaryYear4[$i]</td>
		<td align=center>$teamTotalSalaryYear5[$i]</td>
		<td align=center>$teamTotalSalaryYear6[$i]</td>
        <td bgcolor=#AAA></td>
        <td align=center>$teamTotalPGNextSeasonSalary</td>
        <td align=center>$teamTotalSGNextSeasonSalary</td>
        <td align=center>$teamTotalSFNextSeasonSalary</td>
        <td align=center>$teamTotalPFNextSeasonSalary</td>
        <td align=center>$teamTotalCNextSeasonSalary</td>
        <td bgcolor=#AAA></td>
		<td align=center>$teamFreeAgencySlots[$i]</td>
        <td align=center>$MLEicon</td>
        <td align=center>$LLEicon</td>
	</tr>";

    $i++;
}

$text .= "<table class=\"sortable\" border=1>
	<tr>
		<th>Team</th>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>Total</th>
		<th>" . ($season->endingYear + 1) . "-<br>" . ($season->endingYear + 2) . "<br>Total</th>
		<th>" . ($season->endingYear + 2) . "-<br>" . ($season->endingYear + 3) . "<br>Total</th>
		<th>" . ($season->endingYear + 3) . "-<br>" . ($season->endingYear + 4) . "<br>Total</th>
		<th>" . ($season->endingYear + 4) . "-<br>" . ($season->endingYear + 5) . "<br>Total</th>
		<th>" . ($season->endingYear + 5) . "-<br>" . ($season->endingYear + 6) . "<br>Total</th>
        <td bgcolor=#AAA></td>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>PG</th>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>SG</th>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>SF</th>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>PF</th>
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>C</th>
        <td bgcolor=#AAA></td>
		<th>FA Slots</th>
        <th>Has MLE</th>
        <th>Has LLE</th>
	</tr>
	$table_echo
</table>";
echo $text;

CloseTable();
include "footer.php";

function get_salary($tid)
{
    global $db;

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

function get_salary1($tid)
{
    global $db;

    $queryPlayersUnderContractAfterThisSeason = "SELECT * FROM ibl_plr WHERE retired = 0 AND tid = $tid AND cy <> cyt AND droptime = 0 AND name NOT LIKE '%|%'";
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
