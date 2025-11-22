<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

global $db, $cookie;
$sharedFunctions = new Shared($db);
$commonRepository = new Services\CommonRepository($db);
$season = new Season($db);

 $username = strval($cookie[1] ?? '');
 $userTeam = Team::initialize($db, $commonRepository->getTeamnameFromUsername($username));

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

    $teamTotalAvailableSalaryYear1[$i] = $teamTotalAvailableSalaryYear2[$i] = $teamTotalAvailableSalaryYear3[$i] = 0;
    $teamTotalAvailableSalaryYear4[$i] = $teamTotalAvailableSalaryYear5[$i] = $teamTotalAvailableSalaryYear6[$i] = 0;
    $teamFreeAgencySlots[$i] = 15;

    $salaryCapSpent = $team->getSalaryCapArray($season);
    $team_array1 = get_salary1($team->teamID);

    $teamTotalAvailableSalaryYear1[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year1"];
    $teamTotalAvailableSalaryYear2[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year2"];
    $teamTotalAvailableSalaryYear3[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year3"];
    $teamTotalAvailableSalaryYear4[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year4"];
    $teamTotalAvailableSalaryYear5[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year5"];
    $teamTotalAvailableSalaryYear6[$i] = League::HARD_CAP_MAX - $salaryCapSpent["year6"];

    foreach (JSB::PLAYER_POSITIONS as $position) {
        ${"team" . $position . "sResult"} = $team->getPlayersUnderContractByPositionResult($position);
        ${"teamTotal" . $position . "NextSeasonSalary"} = $team->getTotalNextSeasonSalariesFromPlrResult(${"team" . $position . "sResult"});
    }

    $teamFreeAgencySlots[$i] = $teamFreeAgencySlots[$i] - $team_array1[1]["roster"];

    $MLEicon = ($team->hasMLE == "1") ? "\u{2705}" : "\u{274C}";
    $LLEicon = ($team->hasLLE == "1") ? "\u{2705}" : "\u{274C}";

    ($userTeam->name == $team->name) ? $bgcolor = "bgcolor=FFFFAA" : $bgcolor = "";

    $table_echo .= "<tr>
		<td bgcolor=#$team->color1>
			<a href=\"modules.php?name=Team&op=team&teamID=$team->teamID&display=contracts\">
				<font color=#$team->color2>$team->city $team->name
			</a>
		</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear1[$i]</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear2[$i]</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear3[$i]</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear4[$i]</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear5[$i]</td>
        <td $bgcolor align=center>$teamTotalAvailableSalaryYear6[$i]</td>
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

$beginningYear = ($season->phase == "Free Agency") ? $season->beginningYear + 1 : $season->beginningYear;
$endingYear = ($season->phase == "Free Agency") ? $season->endingYear + 1 : $season->endingYear;

$text .= "<table class=\"sortable\" border=1>
	<tr>
		<th>Team</th>
		<th>" . ($beginningYear + 0) . "-<br>" . ($endingYear + 0) . "<br>Total</th>
		<th>" . ($beginningYear + 1) . "-<br>" . ($endingYear + 1) . "<br>Total</th>
		<th>" . ($beginningYear + 2) . "-<br>" . ($endingYear + 2) . "<br>Total</th>
		<th>" . ($beginningYear + 3) . "-<br>" . ($endingYear + 3) . "<br>Total</th>
		<th>" . ($beginningYear + 4) . "-<br>" . ($endingYear + 4) . "<br>Total</th>
		<th>" . ($beginningYear + 5) . "-<br>" . ($endingYear + 5) . "<br>Total</th>
        <td bgcolor=#AAA></td>
		<th>" . ($beginningYear) . "-<br>" . ($endingYear) . "<br>PG</th>
		<th>" . ($beginningYear) . "-<br>" . ($endingYear) . "<br>SG</th>
		<th>" . ($beginningYear) . "-<br>" . ($endingYear) . "<br>SF</th>
		<th>" . ($beginningYear) . "-<br>" . ($endingYear) . "<br>PF</th>
		<th>" . ($beginningYear) . "-<br>" . ($endingYear) . "<br>C</th>
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
            $yearUnderContract++;
            $contract_amt[$j]["roster"]++;
            $j++;
        }
        $i++;
    }
    return $contract_amt;
}
