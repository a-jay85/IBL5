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
$current_ibl_season=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Current IBL Season' LIMIT 1"),0,"value");
include("header.php");


$query2="SELECT * FROM nuke_ibl_team_info WHERE teamid != 35 ORDER BY teamid ASC";
$result2=mysql_query($query2);
$num2=mysql_num_rows($result2);

OpenTable();

$k=0;
while ($k < $num2)
{
	$teamname[$k]=mysql_result($result2,$k,"team_name");
	$teamcity[$k]=mysql_result($result2,$k,"team_city");
	$teamcolor1[$k]=mysql_result($result2,$k,"color1");
	$teamcolor2[$k]=mysql_result($result2,$k,"color2");
	$teamid[$k]=mysql_result($result2,$k,"teamid");

	$teamsalary1[$k]=0;
	$teamsalary2[$k]=0;
	$teamsalary3[$k]=0;
	$teamsalary4[$k]=0;
	$teamsalary5[$k]=0;
	$teamsalary6[$k]=0;
	$teamslotsfa[$k]=15;
	
	$team_array = get_salary ($teamid[$k], $teamname[$k], $current_ibl_season);
	$team_array1 = get_salary1 ($teamid[$k], $teamname[$k], $current_ibl_season);

	$teamsalary1[$k]=7000-$team_array[1]["salary"];
	$teamsalary2[$k]=7000-$team_array[2]["salary"];
	$teamsalary3[$k]=7000-$team_array[3]["salary"];
	$teamsalary4[$k]=7000-$team_array[4]["salary"];
	$teamsalary5[$k]=7000-$team_array[5]["salary"];
	$teamsalary6[$k]=7000-$team_array[6]["salary"];

	$teamslotsfa[$k]=$teamslotsfa[$k]-$team_array1[1]["roster"];
                
	$table_echo=$table_echo."<tr><td bgcolor=#".$teamcolor1[$k]."><a href=\"modules.php?name=Team&op=team&tid=".$teamid[$k]."\"><font color=#".$teamcolor2[$k].">".$teamcity[$k]." ".$teamname[$k]."</a></td><td".$capnote1.">".$teamsalary1[$k]."</td><td".$capnote2.">".$teamsalary2[$k]."</td><td".$capnote3.">".$teamsalary3[$k]."</td><td".$capnote4.">".$teamsalary4[$k]."</td><td".$capnote5.">".$teamsalary5[$k]."</td><td".$capnote6.">".$teamsalary6[$k]."</td><td>".$teamslotsfa[$k]."</td></tr>";

	$k++;
}

$text=$text."<table class=\"sortable\" border=1>
		  <tr><th>Team</th><th>Year 1</th><th>Year 2</th><th>Year 3</th><th>Year 4</th><th>Year 5</th><th>Year 6</th><th>FA Slots</th></tr>$table_echo</table>";
echo $text;

CloseTable();
include("footer.php");

function get_salary ($tid, $team_name, $current_ibl_season)
{
	$querypicks="SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
	$resultpicks=mysql_query($querypicks);
	$numpicks=mysql_num_rows($resultpicks);
	$hh=0;

	while ($hh < $numpicks)
	{
		$teampick=mysql_result($resultpicks,$hh,"teampick");
		$year=mysql_result($resultpicks,$hh,"year");
		$round=mysql_result($resultpicks,$hh,"round");
		$j=$year-$current_ibl_season+1;
		if ($round == 1)
		{
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		}else{
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		}
		$hh++;
	}
	$query3="SELECT * FROM nuke_iblplyr WHERE retired=0 AND tid = $tid AND cy <> cyt";
	$result3=mysql_query($query3);
	$num3=mysql_num_rows($result3);
	
	$i = 0;
	while ($i < $num3)
	{
		$j = 1;
		$contract_year = mysql_result($result3,$i,"cy");
		$contract_year_total = mysql_result($result3,$i,"cyt");
		while ($contract_year < $contract_year_total)
		{
			$contract_year = $contract_year+1;
			$contract_current_year[$contract_year] = "cy".$contract_year;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"]+mysql_result($result3,$i,$contract_current_year[$contract_year]);
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"]+1;
			$j++;
		}
		$i++;
	}
	return $contract_amt;


}

function get_salary1 ($tid, $team_name, $current_ibl_season)
{
	$querypicks="SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
	$resultpicks=mysql_query($querypicks);
	$numpicks=mysql_num_rows($resultpicks);
	$hh=0;

	while ($hh < $numpicks)
	{
		$teampick=mysql_result($resultpicks,$hh,"teampick");
		$year=mysql_result($resultpicks,$hh,"year");
		$round=mysql_result($resultpicks,$hh,"round");
		$j=$year-$current_ibl_season+1;
		if ($round == 1)
		{
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		}else{
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
			$j++;
			$contract_amt[$j]["salary"]=$contract_amt[$j]["salary"];
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"];
		}
		$hh++;
	}
	$query3="SELECT * FROM nuke_iblplyr WHERE retired=0 AND tid = $tid AND droptime = 0 and name not like '%Buyout%' and cy <> cyt";
	$result3=mysql_query($query3);
	$num3=mysql_num_rows($result3);
	
	$i = 0;
	while ($i < $num3)
	{
		$j = 1;
		$contract_year = mysql_result($result3,$i,"cy");
		$contract_year_total = mysql_result($result3,$i,"cyt");
		while ($contract_year < $contract_year_total)
		{
			$contract_year = $contract_year+1;
			$contract_current_year[$contract_year] = "cy".$contract_year;
			$contract_amt[$j]["salary"] = $contract_amt[$j]["salary"]+mysql_result($result3,$i,$contract_current_year[$contract_year]);
			$contract_amt[$j]["roster"] = $contract_amt[$j]["roster"]+1;
			$j++;
		}
		$i++;
	}
	return $contract_amt;


}
?>