<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

global $db, $cookie;
$sharedFunctions = new Shared($db);
$season = new Season($db);
$isFreeAgencyModuleActive = $sharedFunctions->isFreeAgencyModuleActive();

$username = $cookie[1];
$userTeam = Team::initialize($db, $sharedFunctions->getTeamnameFromUsername($username));

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

Nuke\Header::header();

$resultTeamInfo = League::getAllTeamsResult($db);
$numberOfTeams = $db->sql_numrows($resultTeamInfo);

OpenTable();

$i = 0;
while ($i < $numberOfTeams) {
    $teamRow = $db->sql_fetch_assoc($resultTeamInfo);
    $team = Team::initialize($db, $teamRow);

    foreach (JSB::PLAYER_POSITIONS as $position) {
        ${"team" . $position . "sResult"} = $team->getPlayersUnderContractByPositionResult($position);
        ${"teamTotal" . $position . "NextSeasonSalary"} = $team->getTotalNextSeasonSalariesFromPlrResult(${"team" . $position . "sResult"});
    }

    $teamTotalAvailableSalaryYear1[$i] = 0;
    $teamTotalAvailableSalaryYear2[$i] = 0;
    $teamTotalAvailableSalaryYear3[$i] = 0;
    $teamTotalAvailableSalaryYear4[$i] = 0;
    $teamTotalAvailableSalaryYear5[$i] = 0;
    $teamTotalAvailableSalaryYear6[$i] = 0;
    $teamFreeAgencySlots[$i] = 15;

    $team_array = get_salary($team->teamID);
    $team_array1 = get_salary1($team->teamID);

    $teamTotalAvailableSalaryYear1[$i] = League::HARD_CAP_MAX - $team_array[1]["salary"];
    $teamTotalAvailableSalaryYear2[$i] = League::HARD_CAP_MAX - $team_array[2]["salary"];
    $teamTotalAvailableSalaryYear3[$i] = League::HARD_CAP_MAX - $team_array[3]["salary"];
    $teamTotalAvailableSalaryYear4[$i] = League::HARD_CAP_MAX - $team_array[4]["salary"];
    $teamTotalAvailableSalaryYear5[$i] = League::HARD_CAP_MAX - $team_array[5]["salary"];
    $teamTotalAvailableSalaryYear6[$i] = League::HARD_CAP_MAX - $team_array[6]["salary"];

    $teamFreeAgencySlots[$i] = $teamFreeAgencySlots[$i] - $team_array1[1]["roster"];

    $MLEicon = ($team->hasMLE == "1") ? "\u{2705}" : "\u{274C}";
    $LLEicon = ($team->hasLLE == "1") ? "\u{2705}" : "\u{274C}";

    $teamCurrentSeasonTotalSalary = $team->getTotalCurrentSeasonSalariesFromPlrResult($team->getRosterUnderContractOrderedByNameResult());

    ($userTeam->name == $team->name) ? $bgcolor = "bgcolor=FFFFAA" : $bgcolor = "";

    $table_echo .= "<tr>
		<td bgcolor=#$team->color1>
			<a href=\"modules.php?name=Team&op=team&tid=$team->teamID&display=contracts\">
				<font color=#$team->color2>$team->city $team->name
			</a>
		</td>";

    if (!$isFreeAgencyModuleActive) {
         $table_echo .= "<td $bgcolor align=center>" . (League::HARD_CAP_MAX - $teamCurrentSeasonTotalSalary) . "</td>";
    }

    $table_echo .= "
		<td $bgcolor align=center>$teamTotalAvailableSalaryYear1[$i]</td>
		<td $bgcolor align=center>$teamTotalAvailableSalaryYear2[$i]</td>
		<td $bgcolor align=center>$teamTotalAvailableSalaryYear3[$i]</td>
		<td $bgcolor align=center>$teamTotalAvailableSalaryYear4[$i]</td>
		<td $bgcolor align=center>$teamTotalAvailableSalaryYear5[$i]</td>";

    if ($isFreeAgencyModuleActive) {
        $table_echo .= "<td align=center>$teamTotalAvailableSalaryYear6[$i]</td>";
    }

	$table_echo .= "	
        <td bgcolor=#AAA></td>
        <td $bgcolor align=center>$teamTotalPGNextSeasonSalary</td>
        <td $bgcolor align=center>$teamTotalSGNextSeasonSalary</td>
        <td $bgcolor align=center>$teamTotalSFNextSeasonSalary</td>
        <td $bgcolor align=center>$teamTotalPFNextSeasonSalary</td>
        <td $bgcolor align=center>$teamTotalCNextSeasonSalary</td>
        <td bgcolor=#AAA></td>
		<td $bgcolor align=center>$teamFreeAgencySlots[$i]</td>
        <td $bgcolor align=center>$MLEicon</td>
        <td $bgcolor align=center>$LLEicon</td>
	</tr>";

    $i++;
}

$text .= "<table class=\"sortable\" border=1>
	<tr>
		<th>Team</th>";


if (!$isFreeAgencyModuleActive) {
    $text .= "<th>" . ($season->beginningYear) . "-<br>" . ($season->endingYear) . "<br>Total</th>";
}
		
$text .= "
		<th>" . ($season->endingYear + 0) . "-<br>" . ($season->endingYear + 1) . "<br>Total</th>
		<th>" . ($season->endingYear + 1) . "-<br>" . ($season->endingYear + 2) . "<br>Total</th>
		<th>" . ($season->endingYear + 2) . "-<br>" . ($season->endingYear + 3) . "<br>Total</th>
		<th>" . ($season->endingYear + 3) . "-<br>" . ($season->endingYear + 4) . "<br>Total</th>
		<th>" . ($season->endingYear + 4) . "-<br>" . ($season->endingYear + 5) . "<br>Total</th>";


if ($isFreeAgencyModuleActive) {
    $text .= "<th>" . ($season->endingYear + 5) . "-<br>" . ($season->endingYear + 6) . "<br>Total</th>";
}

$text .= "
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
Nuke\Footer::footer();

function get_salary($tid)
{
    global $db;

    $queryMoneyOwedUnderContractAfterThisSeason = "SELECT * FROM ibl_plr WHERE retired = 0 AND tid = $tid AND cy <> cyt";
    $resultMoneyOwedUnderContractAfterThisSeason = $db->sql_query($queryMoneyOwedUnderContractAfterThisSeason);

    $contract_amt[][] = 0;

    foreach ($resultMoneyOwedUnderContractAfterThisSeason as $contract) {
        $yearUnderContract = $contract['cy'];
        $totalYearsUnderContract = $contract['cyt'];

        $i = 1;
        while ($yearUnderContract < $totalYearsUnderContract) {
            $yearUnderContract++;
            $contractCurrentYear[$yearUnderContract] = "cy" . $yearUnderContract;
            $contract_amt[$i]["salary"] += $contract["$contractCurrentYear[$yearUnderContract]"];
            $contract_amt[$i]["roster"]++;
            $i++;
        }
    }
    return $contract_amt;
}

function get_salary1($tid)
{
    global $db;

    $queryPlayersUnderContractAfterThisSeason = "SELECT * FROM ibl_plr WHERE retired = 0 AND tid = $tid AND cy <> cyt AND name NOT LIKE '%|%'";
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
