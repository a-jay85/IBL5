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
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

/*

$Team_Offering = $_POST['Team_Name'];
$Fields_Counter = $_POST['counterfields'];
$Roster_Slots = $_POST['rosterslots'];
$Healthy_Roster_Slots = $_POST['healthyrosterslots'];
$Type_Of_Action = $_POST['Action'];

if ($Type_Of_Action != NULL)
{

$queryt="SELECT * FROM nuke_ibl_team_info WHERE team_name = '$Team_Offering' ";
$resultt=mysql_query($queryt);

$teamid=mysql_result($resultt,0,"teamid");

$Timestamp = intval(time());

// ADD TEAM TOTAL SALARY FOR THIS YEAR

$querysalary="SELECT * FROM nuke_iblplyr WHERE teamname = '$Team_Offering' AND retired = 0 ";
$results=mysql_query($querysalary);
$num=mysql_numrows($results);
$z=0;

while($z < $num)
	{
		$cy=mysql_result($results,$z,"cy");
		$cyy = "cy$cy";
		$cy2=mysql_result($results,$z,"$cyy");
		$TotalSalary = $TotalSalary + $cy2;
		$z++;
	}

//ENT TEAM TOTAL SALARY FOR THIS YEAR

$k=0;
$Salary=0;

while ($k < $Fields_Counter)
{
$Type=$_POST['type'.$k];
$Salary=$_POST['cy'.$k];
$Index=$_POST['index'.$k];
$Check=$_POST['check'.$k];
$queryn="SELECT * FROM nuke_iblplyr WHERE pid = '$Index' ";
$resultn=mysql_query($queryn);
$playername=mysql_result($resultn,0,"name");
$players_team=mysql_result($resultn,0,"tid");

if ($Check == "on")
	{
	if ($Type_Of_Action == "drop")
	{
		if ($Roster_Slots > 2 and $TotalSalary > 7000)
		{

			echo "You have 12 players and are over $70 mill hard cap.  Therefore you can't drop a player! <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

		} else {

			$queryi = "UPDATE nuke_iblplyr SET `ordinal` = '1000', `droptime` = '$Timestamp' WHERE `pid` = '$Index' LIMIT 1;";
			$resulti=mysql_query($queryi);

			$topicid=32;
			$storytitle=$Team_Offering." make waiver cuts";
			$hometext="The ".$Team_Offering." cut ".$playername." to waivers.";

			// ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE
			$timestamp=date('Y-m-d H:i:s',time());

			$querycat="SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
			$resultcat=mysql_query($querycat);
			$WPMoves=mysql_result($resultcat,0,"counter");
			$catid=mysql_result($resultcat,0,"catid");

			$WPMoves=$WPMoves+1;

			$querycat2="UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
			$resultcat2=mysql_query($querycat2);

			$querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
			$resultstor=mysql_query($querystor);
			echo "<html><head><title>Waiver Processing</title>
			</head>
			<body>
			Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
			</body></html>";

		}

	} else {
		if ($players_team == $teamid)
		{
			$queryi = "UPDATE nuke_iblplyr SET `ordinal` = '800', `teamname` = '$Team_Offering', `tid` = '$teamid' WHERE `pid` = '$Index' LIMIT 1;";
			$resulti=mysql_query($queryi);
			$Roster_Slots++;

			$topicid=33;
			$storytitle=$Team_Offering." make waiver additions";
			$hometext="The ".$Team_Offering." sign ".$playername." from waivers.";

			// ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

			$timestamp=date('Y-m-d H:i:s',time());

			$querycat="SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
			$resultcat=mysql_query($querycat);
			$WPMoves=mysql_result($resultcat,0,"counter");
			$catid=mysql_result($resultcat,0,"catid");

			$WPMoves=$WPMoves+1;

			$querycat2="UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
			$resultcat2=mysql_query($querycat2);

			$querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
			$resultstor=mysql_query($querystor);
			echo "<html><head><title>Waiver Processing</title>
			</head>
			<body>
			Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
			</body></html>";

		} else {

			if ($Healthy_Roster_Slots < 4 and $TotalSalary + $Salary > 7000)
			{

				echo "You have 12 or more healthy players and this signing will put you over $70.  Therefore you can not make this signing. <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

			} elseif ($Healthy_Roster_Slots > 3 and $TotalSalary + $Salary > 7000 and $Salary > 103) {

				echo "You are over the hard cap and therefore can only sign players who are making veteran minimum contract! <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

			} elseif ($Healthy_Roster_Slots < 1) {
				echo "You have full roster of 15 players.  You can't sign another player at this time! <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.";

			} else {

				$queryi = "UPDATE nuke_iblplyr SET `ordinal` = '800', `cy` = '1', `cy1` = '$Salary', `teamname` = '$Team_Offering', `tid` = '$teamid' WHERE `pid` = '$Index' LIMIT 1;";
				$resulti=mysql_query($queryi);
				$Roster_Slots++;

				$topicid=33;
				$storytitle=$Team_Offering." make waiver additions";
				$hometext="The ".$Team_Offering." sign ".$playername." from waivers.";

				// ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

				$timestamp=date('Y-m-d H:i:s',time());

				$querycat="SELECT * FROM nuke_stories_cat WHERE title = 'Waiver Pool Moves'";
				$resultcat=mysql_query($querycat);
				$WPMoves=mysql_result($resultcat,0,"counter");
				$catid=mysql_result($resultcat,0,"catid");

				$WPMoves=$WPMoves+1;

				$querycat2="UPDATE nuke_stories_cat SET counter = $WPMoves WHERE title = 'Waiver Pool Moves'";
				$resultcat2=mysql_query($querycat2);

				$querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
				$resultstor=mysql_query($querystor);
			echo "<html><head><title>Waiver Processing</title>
				</head>
				<body>
				Your waiver moves should now be processed.  <br>You will be automatically redirected to <a href=\"..\">the main IBL page</a> in a moment.  If you are not redirected, click the link.
				</body></html>";

			}
		}
	}
	}
$k++;
}


} // END IF TYPE OF ACTION NOT NULL
*/

//////////

function drafthistory($tid)
{
	global $db;

	include("header.php");
	OpenTable();
	Shared::displaytopmenu($tid);

	$sqlc = "SELECT * FROM nuke_ibl_team_info WHERE teamid = $tid";
	$resultc = $db->sql_query($sqlc);
	$rowc = $db->sql_fetchrow($resultc);
	$teamname = $rowc[team_name];

	$sqld = "SELECT * FROM nuke_iblplyr WHERE draftedby LIKE '$teamname' ORDER BY draftyear DESC, draftround, draftpickno ASC ";
	$resultd = $db->sql_query($sqld);
	$numd = $db->sql_numrows($resultd);

	echo "$teamname Draft History<table class=\"sortable\"><tr><th>Player</th><th>Pos</th><th>Year</th><th>Round</th><th>Pick</th></tr>";

	while($rowd = $db->sql_fetchrow($resultd)) {
		$player_pid = $rowd[pid];
		$player_name = $rowd[name];
		$player_pos = $rowd[pos];
		$player_draftyear = $rowd[draftyear];
		$player_draftround = $rowd[draftround];
		$player_draftpickno = $rowd[draftpickno];
		$player_retired = $rowd[retired];

		if ($player_retired == 1) {
			echo "<tr><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a> (retired)</td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>";
		} else {
			echo "<tr><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player_pid\">$player_name</a></td><td>$player_pos</td><td>$player_draftyear</td><td>$player_draftround</td><td>$player_draftpickno</td></tr>";
		}
	}

	echo "</table>";

	CloseTable();
	include("footer.php");
}

function leaguestats()
{
	include("header.php");
	OpenTable();

	$queryteam="SELECT * FROM nuke_ibl_team_info";
	$resultteam=mysql_query($queryteam);
	$numteams=mysql_num_rows($resultteam);

	$n=0;
	while ($n < $numteams) {
		$teamid[$n]=mysql_result($resultteam,$n,"teamid");
		$team_city[$n]=mysql_result($resultteam,$n,"team_city");
		$team_name[$n]=mysql_result($resultteam,$n,"team_name");
		$coach_pts[$n]=mysql_result($resultteam,$n,"Contract_Coach");
		$color1[$n]=mysql_result($resultteam,$n,"color1");
		$color2[$n]=mysql_result($resultteam,$n,"color2");
		$n++;
	}

	$queryTeamOffenseTotals="SELECT * FROM ibl_team_offense_stats ORDER BY team ASC";
	$resultTeamOffenseTotals=mysql_query($queryTeamOffenseTotals);
	$numTeamOffenseTotals=mysql_numrows($resultTeamOffenseTotals);

	$t=0;

	while ($t < $numTeamOffenseTotals) {
		$team_off_name=mysql_result($resultTeamOffenseTotals,$t,"team");
		$m=0;
		while ($m < $n) {
			if ($team_off_name == $team_name[$m]) {
				$teamcolor1=$color1[$m];
				$teamcolor2=$color2[$m];
				$teamcity=$team_city[$m];
				$tid=$teamid[$m];
			}
			$m++;
		}

		$team_off_games=mysql_result($resultTeamOffenseTotals,$t,"games");
		$team_off_minutes=mysql_result($resultTeamOffenseTotals,$t,"minutes");
		$team_off_fgm=mysql_result($resultTeamOffenseTotals,$t,"fgm");
		$team_off_fga=mysql_result($resultTeamOffenseTotals,$t,"fga");
		$team_off_ftm=mysql_result($resultTeamOffenseTotals,$t,"ftm");
		$team_off_fta=mysql_result($resultTeamOffenseTotals,$t,"fta");
		$team_off_tgm=mysql_result($resultTeamOffenseTotals,$t,"tgm");
		$team_off_tga=mysql_result($resultTeamOffenseTotals,$t,"tga");
		$team_off_orb=mysql_result($resultTeamOffenseTotals,$t,"orb");
		$team_off_reb=mysql_result($resultTeamOffenseTotals,$t,"reb");
		$team_off_ast=mysql_result($resultTeamOffenseTotals,$t,"ast");
		$team_off_stl=mysql_result($resultTeamOffenseTotals,$t,"stl");
		$team_off_tvr=mysql_result($resultTeamOffenseTotals,$t,"tvr");
		$team_off_blk=mysql_result($resultTeamOffenseTotals,$t,"blk");
		$team_off_pf=mysql_result($resultTeamOffenseTotals,$t,"pf");
		$team_off_pts=$team_off_fgm+$team_off_fgm+$team_off_ftm+$team_off_tgm;

		@$team_off_avgfgm=number_format($team_off_fgm/$team_off_games,2);
		@$team_off_avgfga=number_format($team_off_fga/$team_off_games,2);
		@$team_off_fgp=number_format($team_off_fgm/$team_off_fga,3);
		@$team_off_avgftm=number_format($team_off_ftm/$team_off_games,2);
		@$team_off_avgfta=number_format($team_off_fta/$team_off_games,2);
		@$team_off_ftp=number_format($team_off_ftm/$team_off_fta,3);
		@$team_off_avgtgm=number_format($team_off_tgm/$team_off_games,2);
		@$team_off_avgtga=number_format($team_off_tga/$team_off_games,2);
		@$team_off_tgp=number_format($team_off_tgm/$team_off_tga,3);
		@$team_off_avgorb=number_format($team_off_orb/$team_off_games,2);
		@$team_off_avgreb=number_format($team_off_reb/$team_off_games,2);
		@$team_off_avgast=number_format($team_off_ast/$team_off_games,2);
		@$team_off_avgstl=number_format($team_off_stl/$team_off_games,2);
		@$team_off_avgtvr=number_format($team_off_tvr/$team_off_games,2);
		@$team_off_avgblk=number_format($team_off_blk/$team_off_games,2);
		@$team_off_avgpf=number_format($team_off_pf/$team_off_games,2);
		@$team_off_avgpts=number_format($team_off_pts/$team_off_games,2);

		$lg_off_games=$lg_off_games+$team_off_games;
		$lg_off_minutes=$lg_off_minutes+$team_off_minutes;
		$lg_off_fgm=$lg_off_fgm+$team_off_fgm;
		$lg_off_fga=$lg_off_fga+$team_off_fga;
		$lg_off_ftm=$lg_off_ftm+$team_off_ftm;
		$lg_off_fta=$lg_off_fta+$team_off_fta;
		$lg_off_tgm=$lg_off_tgm+$team_off_tgm;
		$lg_off_tga=$lg_off_tga+$team_off_tga;
		$lg_off_orb=$lg_off_orb+$team_off_orb;
		$lg_off_reb=$lg_off_reb+$team_off_reb;
		$lg_off_ast=$lg_off_ast+$team_off_ast;
		$lg_off_stl=$lg_off_stl+$team_off_stl;
		$lg_off_tvr=$lg_off_tvr+$team_off_tvr;
		$lg_off_blk=$lg_off_blk+$team_off_blk;
		$lg_off_pf=$lg_off_pf+$team_off_pf;
		$lg_off_pts=$lg_off_pts+$team_off_pts;

		$offense_totals=$offense_totals."<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
			<td>$team_off_games</td>
			<td>$team_off_fgm</td>
			<td>$team_off_fga</td>
			<td>$team_off_ftm</td>
			<td>$team_off_fta</td>
			<td>$team_off_tgm</td>
			<td>$team_off_tga</td>
			<td>$team_off_orb</td>
			<td>$team_off_reb</td>
			<td>$team_off_ast</td>
			<td>$team_off_stl</td>
			<td>$team_off_tvr</td>
			<td>$team_off_blk</td>
			<td>$team_off_pf</td>
			<td>$team_off_pts</td>
		</tr>";

		$offense_averages=$offense_averages."<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_off_name Offense</font></a></td>
			<td>$team_off_avgfgm</td>
			<td>$team_off_avgfga</td>
			<td>$team_off_fgp</td>
			<td>$team_off_avgftm</td>
			<td>$team_off_avgfta</td>
			<td>$team_off_ftp</td>
			<td>$team_off_avgtgm</td>
			<td>$team_off_avgtga</td>
			<td>$team_off_tgp</td>
			<td>$team_off_avgorb</td>
			<td>$team_off_avgreb</td>
			<td>$team_off_avgast</td>
			<td>$team_off_avgstl</td>
			<td>$team_off_avgtvr</td>
			<td>$team_off_avgblk</td>
			<td>$team_off_avgpf</td>
			<td>$team_off_avgpts</td>
		</tr>";

		$t++;
	}

	$queryTeamDefenseTotals="SELECT * FROM ibl_team_defense_stats ORDER BY team ASC";
	$resultTeamDefenseTotals=mysql_query($queryTeamDefenseTotals);
	$numTeamDefenseTotals=mysql_numrows($resultTeamDefenseTotals);

	$t=0;

	while ($t < $numTeamDefenseTotals) {

		$team_def_name=mysql_result($resultTeamDefenseTotals,$t,"team");
		$m=0;
		while ($m < $n) {
			if ($team_def_name == $team_name[$m]) {
				$teamcolor1=$color1[$m];
				$teamcolor2=$color2[$m];
				$teamcity=$team_city[$m];
				$tid=$teamid[$m];
			}
			$m++;
		}

		$team_def_games=mysql_result($resultTeamDefenseTotals,$t,"games");
		$team_def_minutes=mysql_result($resultTeamDefenseTotals,$t,"minutes");
		$team_def_fgm=mysql_result($resultTeamDefenseTotals,$t,"fgm");
		$team_def_fga=mysql_result($resultTeamDefenseTotals,$t,"fga");
		$team_def_ftm=mysql_result($resultTeamDefenseTotals,$t,"ftm");
		$team_def_fta=mysql_result($resultTeamDefenseTotals,$t,"fta");
		$team_def_tgm=mysql_result($resultTeamDefenseTotals,$t,"tgm");
		$team_def_tga=mysql_result($resultTeamDefenseTotals,$t,"tga");
		$team_def_orb=mysql_result($resultTeamDefenseTotals,$t,"orb");
		$team_def_reb=mysql_result($resultTeamDefenseTotals,$t,"reb");
		$team_def_ast=mysql_result($resultTeamDefenseTotals,$t,"ast");
		$team_def_stl=mysql_result($resultTeamDefenseTotals,$t,"stl");
		$team_def_tvr=mysql_result($resultTeamDefenseTotals,$t,"tvr");
		$team_def_blk=mysql_result($resultTeamDefenseTotals,$t,"blk");
		$team_def_pf=mysql_result($resultTeamDefenseTotals,$t,"pf");
		$team_def_pts=$team_def_fgm+$team_def_fgm+$team_def_ftm+$team_def_tgm;

		@$team_def_avgfgm=number_format($team_def_fgm/$team_def_games,2);
		@$team_def_avgfga=number_format($team_def_fga/$team_def_games,2);
		@$team_def_fgp=number_format($team_def_fgm/$team_def_fga,3);
		@$team_def_avgftm=number_format($team_def_ftm/$team_def_games,2);
		@$team_def_avgfta=number_format($team_def_fta/$team_def_games,2);
		@$team_def_ftp=number_format($team_def_ftm/$team_def_fta,3);
		@$team_def_avgtgm=number_format($team_def_tgm/$team_def_games,2);
		@$team_def_avgtga=number_format($team_def_tga/$team_def_games,2);
		@$team_def_tgp=number_format($team_def_tgm/$team_def_tga,3);
		@$team_def_avgorb=number_format($team_def_orb/$team_def_games,2);
		@$team_def_avgreb=number_format($team_def_reb/$team_def_games,2);
		@$team_def_avgast=number_format($team_def_ast/$team_def_games,2);
		@$team_def_avgstl=number_format($team_def_stl/$team_def_games,2);
		@$team_def_avgtvr=number_format($team_def_tvr/$team_def_games,2);
		@$team_def_avgblk=number_format($team_def_blk/$team_def_games,2);
		@$team_def_avgpf=number_format($team_def_pf/$team_def_games,2);
		@$team_def_avgpts=number_format($team_def_pts/$team_def_games,2);

		$defense_totals=$defense_totals."<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
			<td>$team_def_games</td>
			<td>$team_def_fgm</td>
			<td>$team_def_fga</td>
			<td>$team_def_ftm</td>
			<td>$team_def_fta</td>
			<td>$team_def_tgm</td>
			<td>$team_def_tga</td>
			<td>$team_def_orb</td>
			<td>$team_def_reb</td>
			<td>$team_def_ast</td>
			<td>$team_def_stl</td>
			<td>$team_def_tvr</td>
			<td>$team_def_blk</td>
			<td>$team_def_pf</td>
			<td>$team_def_pts</td>
		</tr>";

		$defense_averages=$defense_averages."<tr>
			<td bgcolor=\"$teamcolor1\"><a href=\"modules.php?name=Team&op=team&tid=$tid\"><font color=\"$teamcolor2\">$teamcity $team_def_name Defense</font></a></td>
			<td>$team_def_avgfgm</td>
			<td>$team_def_avgfga</td>
			<td>$team_def_fgp</td>
			<td>$team_def_avgftm</td>
			<td>$team_def_avgfta</td>
			<td>$team_def_ftp</td>
			<td>$team_def_avgtgm</td>
			<td>$team_def_avgtga</td>
			<td>$team_def_tgp</td>
			<td>$team_def_avgorb</td>
			<td>$team_def_avgreb</td>
			<td>$team_def_avgast</td>
			<td>$team_def_avgstl</td>
			<td>$team_def_avgtvr</td>
			<td>$team_def_avgblk</td>
			<td>$team_def_avgpf</td>
			<td>$team_def_avgpts</td>
		</tr>";

		$t++;
	}

	@$lg_off_avgfgm=number_format($lg_off_fgm/$lg_off_games,2);
	@$lg_off_avgfga=number_format($lg_off_fga/$lg_off_games,2);
	@$lg_off_fgp=number_format($lg_off_fgm/$lg_off_fga,3);
	@$lg_off_avgftm=number_format($lg_off_ftm/$lg_off_games,2);
	@$lg_off_avgfta=number_format($lg_off_fta/$lg_off_games,2);
	@$lg_off_ftp=number_format($lg_off_ftm/$lg_off_fta,3);
	@$lg_off_avgtgm=number_format($lg_off_tgm/$lg_off_games,2);
	@$lg_off_avgtga=number_format($lg_off_tga/$lg_off_games,2);
	@$lg_off_tgp=number_format($lg_off_tgm/$lg_off_tga,3);
	@$lg_off_avgorb=number_format($lg_off_orb/$lg_off_games,2);
	@$lg_off_avgreb=number_format($lg_off_reb/$lg_off_games,2);
	@$lg_off_avgast=number_format($lg_off_ast/$lg_off_games,2);
	@$lg_off_avgstl=number_format($lg_off_stl/$lg_off_games,2);
	@$lg_off_avgtvr=number_format($lg_off_tvr/$lg_off_games,2);
	@$lg_off_avgblk=number_format($lg_off_blk/$lg_off_games,2);
	@$lg_off_avgpf=number_format($lg_off_pf/$lg_off_games,2);
	@$lg_off_avgpts=number_format($lg_off_pts/$lg_off_games,2);

	$league_totals="<tr style=\"font-weight:bold\">
		<td>LEAGUE TOTALS</td>
		<td>$lg_off_games</td>
		<td>$lg_off_fgm</td>
		<td>$lg_off_fga</td>
		<td>$lg_off_ftm</td>
		<td>$lg_off_fta</td>
		<td>$lg_off_tgm</td>
		<td>$lg_off_tga</td>
		<td>$lg_off_orb</td>
		<td>$lg_off_reb</td>
		<td>$lg_off_ast</td>
		<td>$lg_off_stl</td>
		<td>$lg_off_tvr</td>
		<td>$lg_off_blk</td>
		<td>$lg_off_pf</td>
		<td>$lg_off_pts</td>
	</tr>";

	$league_averages="<tr style=\"font-weight:bold\">
		<td>LEAGUE AVERAGES</td>
		<td>$lg_off_avgfgm</td>
		<td>$lg_off_avgfga</td>
		<td>$lg_off_fgp</td>
		<td>$lg_off_avgftm</td>
		<td>$lg_off_avgfta</td>
		<td>$lg_off_ftp</td>
		<td>$lg_off_avgtgm</td>
		<td>$lg_off_avgtga</td>
		<td>$lg_off_tgp</td>
		<td>$lg_off_avgorb</td>
		<td>$lg_off_avgreb</td>
		<td>$lg_off_avgast</td>
		<td>$lg_off_avgstl</td>
		<td>$lg_off_avgtvr</td>
		<td>$lg_off_avgblk</td>
		<td>$lg_off_avgpf</td>
		<td>$lg_off_avgpts</td>
	</tr>";

	echo "<center>
		<h1>League-wide Statistics</h1>

		<h2>Team Offense Totals</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$offense_totals</tbody>
		<tfoot>$league_totals</tfoot>
		</table>

		<h2>Team Defense Totals</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>Gm</th><th>FGM</th><th>FGA</th><th>FTM</th><th>FTA</th><th>3GM</th><th>3GA</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$defense_totals</tbody>
		<tfoot>$league_totals</tfoot>
		</table>

		<h2>Team Offense Averages</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$offense_averages</tbody>
		<tfoot>$league_averages</tfoot>
		</table>

		<h2>Team Defense Averages</h2>
		<table class=\"sortable\">
		<thead><tr><th>Team</th><th>FGM</th><th>FGA</th><th>FGP</th><th>FTM</th><th>FTA</th><th>FTP</th><th>3GM</th><th>3GA</th><th>3GP</th><th>ORB</th><th>REB</th><th>AST</th><th>STL</th><th>TVR</th><th>BLK</th><th>PF</th><th>PTS</th></tr></thead>
		<tbody>$defense_averages</tbody>
		<tfoot>$league_averages</tfoot>
		</table>";

	CloseTable();
	include("footer.php");
}

function schedule($tid)
{
	global $db;
	$tid = intval($tid);
	include("header.php");
	OpenTable();
//============================
// GRAB TEAM COLORS, ET AL
//============================
	$queryteam = "SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid';";
	$resultteam = mysql_query($queryteam);
	$teamid = mysql_result($resultteam, 0, "teamid");
	$team_city = mysql_result($resultteam, 0, "team_city");
	$team_name = mysql_result($resultteam, 0, "team_name");
	$coach_pts = mysql_result($resultteam, 0, "Contract_Coach");
	$color1 = mysql_result($resultteam, 0, "color1");
	$color2 = mysql_result($resultteam, 0, "color2");
//=============================
//DISPLAY TOP MENU
//=============================
	Shared::displaytopmenu($tid);
	$query = "SELECT * FROM `ibl_schedule` WHERE Visitor = $tid OR Home = $tid ORDER BY Date ASC;";
	$result = mysql_query($query);
	$num = mysql_numrows($result);
	$year = mysql_result($result, 0, "Year");
	$year1 = $year + 1;
	$wins = 0;
	$losses = 0;
	echo "<center>
		<img src=\"./images/logo/$tid.jpg\">
		<table width=600 border=1>
			<tr bgcolor=$color1><td colspan=26><center><font color=$color2><h1>Team Schedule</h1><p><i>games highlighted in yellow are projected to be run next sim (7 days)</i></font></center></td></tr>
			<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>November</center></b></font></td></tr>
			<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year, '11', $tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>December</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year, '12', $tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>January</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '01',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>February</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '02',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>March</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '03',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>April</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '04',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color2><td colspan=26><font color=$color1><b><center>May</center></b></font></td></tr>
		<tr bgcolor=$color2><td><font color=$color1><b>Date</font></td><td><font color=$color1><b>Visitor</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Home</font></td><td><font color=$color1><b>Score</font></td><td><font color=$color1><b>Box Score</b></font></td><td><font color=$color1><b>Record</font></td><td><font color=$color1><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '05',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "<tr bgcolor=$color1><td colspan=26><font color=$color2><b><center>Playoffs</center></b></font></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b>Date</font></td><td><font color=$color2><b>Visitor</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Home</font></td><td><font color=$color2><b>Score</font></td><td><font color=$color2><b>Box Score</b></font></td><td><font color=$color2><b>Record</font></td><td><font color=$color2><b>Streak</font></td></tr>";
	list ($wins, $losses, $winStreak, $lossStreak) = boxscore($year1, '06',$tid, $wins, $losses, $winStreak, $lossStreak);
	echo "</center>";
	CloseTable();

	CloseTable();
	include("footer.php");
}

function boxscore($year, $month, $tid, $wins, $losses, $winStreak, $lossStreak)
{
	$query = "SELECT *
		FROM `ibl_schedule`
		WHERE (Visitor = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31')
			OR (Home = $tid AND Date BETWEEN '$year-$month-01' AND '$year-$month-31')
		ORDER BY Date ASC";
	$result = mysql_query($query);
	$num = mysql_numrows($result);
	$i = 0;

	$teamSeasonRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC;";
	$teamSeasonRecordsResult = mysql_query($teamSeasonRecordsQuery);

	$arrayLastSimDates = Shared::getLastSimDatesArray();
	$lastSimStartDate = date_create($arrayLastSimDates["Start Date"]);
	$lastSimEndDate = date_create($arrayLastSimDates["End Date"]);
	$projectedNextSimEndDate = date_format(date_add($lastSimEndDate, date_interval_create_from_date_string('7 days')), 'Y-m-d');

	while ($i < $num) {
		$date = mysql_result($result, $i, "Date");
		$visitor = mysql_result($result, $i, "Visitor");
		$visitorScore = mysql_result($result, $i, "VScore");
		$home = mysql_result($result, $i, "Home");
		$homeScore = mysql_result($result, $i, "HScore");
		$boxID = mysql_result($result, $i, "BoxID");

		$visitorTeamname = Shared::getTeamnameFromTid($visitor);
		$homeTeamname = Shared::getTeamnameFromTid($home);
		$visitorRecord = mysql_result($teamSeasonRecordsResult, $visitor-1, "leagueRecord");
		$homeRecord = mysql_result($teamSeasonRecordsResult, $home-1, "leagueRecord");

		if ($visitorScore == $homeScore) {
			if ($date <= $projectedNextSimEndDate) {
				echo "<tr bgcolor=#DDDD00>";
			} else {
				echo "<tr>";
			}
			echo "<td>$date</td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname ($visitorRecord)</a></td>
				<td></td>
				<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname ($homeRecord)</a></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
			</tr>";
		} else {
			if ($tid == $visitor) {
				if ($visitorScore > $homeScore) {
					$wins++;
					$winStreak++;
					$lossStreak = 0;
					$winlosscolor = "green";
				} else {
					$losses++;
					$lossStreak++;
					$winStreak = 0;
					$winlosscolor = "red";
				}
			} else {
				if ($visitorScore > $homeScore) {
					$losses++;
					$lossStreak++;
					$winStreak = 0;
					$winlosscolor = "red";
				} else {
					$wins++;
					$winStreak++;
					$lossStreak = 0;
					$winlosscolor = "green";
				}
			}

			if ($winStreak > $lossStreak) {
				$streak = "W $winStreak";
			} else {
				$streak = "L $lossStreak";
			}

			(($i % 2) == 0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

			if ($visitorScore > $homeScore) {
				echo "<tr bgcolor=$bgcolor>
					<td>$date</td>
					<td><b><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname</a></b></td>
					<td><b><font color=$winlosscolor>$visitorScore</font></b></td>
					<td><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname</a></td>
					<td><b><font color=$winlosscolor>$homeScore</font></b></td>
					<td><a href=\"./ibl/IBL/box$boxID.htm\">View</a></td>
					<td>$wins - $losses</td>
					<td>$streak</td>
				</tr>";
			} else if ($visitorScore < $homeScore) {
				echo "<tr bgcolor=$bgcolor>
					<td>$date</td>
					<td><a href=\"modules.php?name=Team&op=team&tid=$visitor\">$visitorTeamname</a></td>
					<td><b><font color=$winlosscolor>$visitorScore</font></b></td>
					<td><b><a href=\"modules.php?name=Team&op=team&tid=$home\">$homeTeamname</a></b></td>
					<td><b><font color=$winlosscolor>$homeScore</font></b></td>
					<td><a href=\"./ibl/IBL/box$boxID.htm\">View</a></td>
					<td>$wins - $losses</td>
					<td>$streak</td>
				</tr>";
			}
		}

		$i++;
	}

	return array($wins, $losses, $winStreak, $lossStreak);
}

function finances($tid)
{
	global $db;

	$tid = intval($tid);
	$yr = $_REQUEST['yr'];

	$queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
	$resultteam=mysql_query($queryteam);

	$teamid=mysql_result($resultteam,0,"teamid");
	$team_city=mysql_result($resultteam,0,"team_city");
	$team_name=mysql_result($resultteam,0,"team_name");
	$coach_pts=mysql_result($resultteam,0,"Contract_Coach");
	$color1=mysql_result($resultteam,0,"color1");
	$color2=mysql_result($resultteam,0,"color2");
	$owner_name=mysql_result($resultteam,0,"owner_name");
	$owner_email=mysql_result($resultteam,0,"owner_email");
	$icq=mysql_result($resultteam,0,"icq");
	$aim=mysql_result($resultteam,0,"aim");
	$msn=mysql_result($resultteam,0,"msn");

	include("teamcap.php");
	include("header.php");

	$matrix=teamcap($tid);

	$returned_stuff=financialdisplay($matrix,$yr,$tid);
	$output=$returned_stuff[0];
	$bottom_output=$returned_stuff[1];

	// END NEW CBA FINANCIAL PLANNER

	OpenTable();

	Shared::displaytopmenu($tid);

	echo "<table valign=top align=center><tr><td align=center valign=top><img src=\"./images/logo/$tid.jpg\"></td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b><center>$team_city $team_name Finances (Cap Space)</center></b></font></td></tr>
		<tr><td align=center><table border=1 cellpadding=0><tr>$bottom_output</tr></table></td></tr>";
	echo $output[$yr];
	echo "</td></tr></table></td></tr></table>";

	CloseTable();
	include("footer.php");
}

function team($tid)
{
	global $db;
	$tid = intval($tid);

	$yr = $_REQUEST['yr'];
	$fayr = $_REQUEST['fayr'];

	$display = $_REQUEST['display'];
	if ($display == NULL) $display="ratings";

	include("header.php");
	OpenTable();

	//============================
	// GRAB TEAM COLORS, ET AL
	//============================

	$queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
	$resultteam=mysql_query($queryteam);

	$teamid=mysql_result($resultteam,0,"teamid");
	$team_city=mysql_result($resultteam,0,"team_city");
	$team_name=mysql_result($resultteam,0,"team_name");
	$coach_pts=mysql_result($resultteam,0,"Contract_Coach");
	$color1=mysql_result($resultteam,0,"color1");
	$color2=mysql_result($resultteam,0,"color2");
	$owner_name=mysql_result($resultteam,0,"owner_name");
	$owner_email=mysql_result($resultteam,0,"owner_email");
	$icq=mysql_result($resultteam,0,"icq");
	$aim=mysql_result($resultteam,0,"aim");
	$msn=mysql_result($resultteam,0,"msn");
	$Extension=mysql_result($resultteam,0,"Used_Extension_This_Season");

	if ($Extension == 0) {
		$Extension_Text="contract extension still available";
	} else {
		$Extension_Text="contract extension used for this season";
	}

	//=============================
	//DISPLAY TOP MENU
	//=============================

	Shared::displaytopmenu($tid);

	//=============================
	//GET CONTRACT AMOUNTS CORRECT
	//=============================

	$queryfaon="SELECT * FROM nuke_modules WHERE mid = '83' ORDER BY title ASC"; // THIS CHECKS IF FA IS ACTIVE AND HIDES FA PLAYERS IF IT IS
	$resultfaon=mysql_query($queryfaon);
	$numfaon=mysql_numrows($resultfaon);
	$faon=mysql_result($resultfaon,0,"active");

	if ($tid == 0) { // Team 0 is the Free Agents; we want a query that will pick up all of their players.
		if ($faon==0) {
			$query="SELECT * FROM nuke_iblplyr WHERE ordinal > '959' AND retired = 0 ORDER BY ordinal ASC";
//			$query="SELECT * FROM nuke_iblplyr WHERE tid = 0 AND retired = 0 ORDER BY ordinal ASC";
		} else {
			$query="SELECT * FROM nuke_iblplyr WHERE ordinal > '959' AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
//			$query="SELECT * FROM nuke_iblplyr WHERE tid = 0 AND retired = 0 AND cyt != cy ORDER BY ordinal ASC";
		}
		$result=mysql_query($query);
		$num=mysql_numrows($result);
	} else if ($tid == "-1") { // SHOW ENTIRE LEAGUE
		$query="SELECT * FROM nuke_iblplyr WHERE retired = 0 AND name NOT LIKE '%Buyouts' ORDER BY ordinal ASC";
		$result=mysql_query($query);
		$num=mysql_numrows($result);
	} else { // If not Free Agents, use the code below instead.
		if ($yr != "") {
			$query="SELECT * FROM nuke_iblhist WHERE teamid = '$tid' AND year = '$yr' ORDER BY name ASC";
		} else if ($faon==0) {
			$query="SELECT * FROM nuke_iblplyr WHERE tid = '$tid' AND retired = 0 ORDER BY name ASC";
		} else {
			$query="SELECT * FROM nuke_iblplyr WHERE tid = '$tid' AND retired = 0 AND cyt != cy ORDER BY name ASC";
		}
		$result=mysql_query($query);
		$num=mysql_numrows($result);
	}

	echo "<table><tr><td align=center valign=top><img src=\"./images/logo/$tid.jpg\">";

	/* =================== INSERT STARTERS =========== */

	$startingPG=NULL;
	$startingSG=NULL;
	$startingSF=NULL;
	$startingPF=NULL;
	$startingC=NULL;

	$startingPGpid=NULL;
	$startingSGpid=NULL;
	$startingSFpid=NULL;
	$startingPFpid=NULL;
	$startingCpid=NULL;

	$s=0;

	while ($s < $num) {
		if (mysql_result($result,$s,"PGDepth")==1) {
			$startingPG=mysql_result($result,$s,"name");
			$startingPGpid=mysql_result($result,$s,"pid");
		}
		if (mysql_result($result,$s,"SGDepth") == 1) {
			$startingSG=mysql_result($result,$s,"name");
			$startingSGpid=mysql_result($result,$s,"pid");
		}
		if (mysql_result($result,$s,"SFDepth") == 1) {
			$startingSF=mysql_result($result,$s,"name");
			$startingSFpid=mysql_result($result,$s,"pid");
		}
		if (mysql_result($result,$s,"PFDepth") == 1) {
			$startingPF=mysql_result($result,$s,"name");
			$startingPFpid=mysql_result($result,$s,"pid");
		}
		if (mysql_result($result,$s,"CDepth") == 1) {
			$startingC=mysql_result($result,$s,"name");
			$startingCpid=mysql_result($result,$s,"pid");
		}
		$s++;
	}
	if ($yr != "") {
		echo "<center><h1>$yr $team_name</h1></center>";
	}
	if ($tid != 0 AND $yr == "") {
		$starters_table="<table align=\"center\" border=1 cellpadding=1 cellspacing=1>
			<tr bgcolor=$color1>
				<td colspan=5><font color=$color2><center><b>Last Sim's Starters</b></center></font></td>
			</tr>
			<tr>
				<td><center><b>PG</b><br><img src=\"./images/player/$startingPGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPGpid\">$startingPG</a></td>
				<td><center><b>SG</b><br><img src=\"./images/player/$startingSGpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSGpid\">$startingSG</a></td>
				<td><center><b>SF</b><br><img src=\"./images/player/$startingSFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingSFpid\">$startingSF</a></td>
				<td><center><b>PF</b><br><img src=\"./images/player/$startingPFpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingPFpid\">$startingPF</a></td>
				<td><center><b>C</b><br><img src=\"./images/player/$startingCpid.jpg\" height=\"90\" width=\"65\"><br><a href=\"./modules.php?name=Player&pa=showpage&pid=$startingCpid\">$startingC</a></td>
			</tr>
		</table>";
	}

	// END OF INSERTION OF STARTERS

	// BEGIN RATINGS TABLE

	$table_ratings="<table  align=\"center\" class=\"sortable\">
		<colgroup span=2><colgroup span=2><colgroup span=6><colgroup span=6><colgroup span=4><colgroup span=4><colgroup span=1>
		<thead bgcolor=$color1>
			<tr bgcolor=$color1>
				<th><font color=$color2>Pos</font></th>
				<th><font color=$color2>Player</font></th>
				<th><font color=$color2>Age</font></th>
				<th><font color=$color2>2ga</font></th>
				<th><font color=$color2>2g%</font></th>
				<th><font color=$color2>fta</font></th>
				<th><font color=$color2>ft%</font></th>
				<th><font color=$color2>3ga</font></th>
				<th><font color=$color2>3g%</font></th>
				<th><font color=$color2>orb</font></th>
				<th><font color=$color2>drb</font></th>
				<th><font color=$color2>ast</font></th>
				<th><font color=$color2>stl</font></th>
				<th><font color=$color2>tvr</font></th>
				<th><font color=$color2>blk</font></th>
				<th><font color=$color2>foul</font></th>
				<th><font color=$color2>oo</font></th>
				<th><font color=$color2>do</font></th>
				<th><font color=$color2>po</font></th>
				<th><font color=$color2>to</font></th>
				<th><font color=$color2>od</font></th>
				<th><font color=$color2>dd</font></th>
				<th><font color=$color2>pd</font></th>
				<th><font color=$color2>td</font></th>
				<th><font color=$color2>Inj</font></th>
			</tr>
		</thead>
	<tbody>";

	$i=0;

	while ($i < $num) {
		if ($yr == "") {
			$name=mysql_result($result,$i,"name");
			$team=mysql_result($result,$i,"teamname");
			$pid=mysql_result($result,$i,"pid");
			$pos=mysql_result($result,$i,"pos");
			$p_ord=mysql_result($result,$i,"ordinal");
			$age=mysql_result($result,$i,"age");
			$inj=mysql_result($result,$i,"injured");

			$r_2ga=mysql_result($result,$i,"r_fga");
			$r_2gp=mysql_result($result,$i,"r_fgp");
			$r_fta=mysql_result($result,$i,"r_fta");
			$r_ftp=mysql_result($result,$i,"r_ftp");
			$r_3ga=mysql_result($result,$i,"r_tga");
			$r_3gp=mysql_result($result,$i,"r_tgp");
			$r_orb=mysql_result($result,$i,"r_orb");
			$r_drb=mysql_result($result,$i,"r_drb");
			$r_ast=mysql_result($result,$i,"r_ast");
			$r_stl=mysql_result($result,$i,"r_stl");
			$r_blk=mysql_result($result,$i,"r_blk");
			$r_tvr=mysql_result($result,$i,"r_to");
			$r_foul=mysql_result($result,$i,"r_foul");
			$r_oo=mysql_result($result,$i,"oo");
			$r_do=mysql_result($result,$i,"do");
			$r_po=mysql_result($result,$i,"po");
			$r_to=mysql_result($result,$i,"to");
			$r_od=mysql_result($result,$i,"od");
			$r_dd=mysql_result($result,$i,"dd");
			$r_pd=mysql_result($result,$i,"pd");
			$r_td=mysql_result($result,$i,"td");
			$r_bird=mysql_result($result,$i,"bird");

			$draftyear=mysql_result($result,$i,"draftyear");
			$exp=mysql_result($result,$i,"exp");
			$cy=mysql_result($result,$i,"cy");
			$cyt=mysql_result($result,$i,"cyt");

			$yearoffreeagency=$draftyear+$exp+$cyt-$cy;
		} else {
			$name=mysql_result($result,$i,"name");
			$team=mysql_result($result,$i,"team");
			$pid=mysql_result($result,$i,"pid");

			$r_2ga=mysql_result($result,$i,"r_2ga");
			$r_2gp=mysql_result($result,$i,"r_2gp");
			$r_fta=mysql_result($result,$i,"r_fta");
			$r_ftp=mysql_result($result,$i,"r_ftp");
			$r_3ga=mysql_result($result,$i,"r_3ga");
			$r_3gp=mysql_result($result,$i,"r_3gp");
			$r_orb=mysql_result($result,$i,"r_orb");
			$r_drb=mysql_result($result,$i,"r_drb");
			$r_ast=mysql_result($result,$i,"r_ast");
			$r_stl=mysql_result($result,$i,"r_stl");
			$r_blk=mysql_result($result,$i,"r_blk");
			$r_tvr=mysql_result($result,$i,"r_tvr");
			$r_oo=mysql_result($result,$i,"r_oo");
			$r_do=mysql_result($result,$i,"r_do");
			$r_po=mysql_result($result,$i,"r_po");
			$r_to=mysql_result($result,$i,"r_to");
			$r_od=mysql_result($result,$i,"r_od");
			$r_dd=mysql_result($result,$i,"r_dd");
			$r_pd=mysql_result($result,$i,"r_pd");
			$r_td=mysql_result($result,$i,"r_td");
		}

		(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

		$table_ratings .= "<tr bgcolor=$bgcolor>
			<td>$pos</td>";

		if ($tid == 0) {
			$table_ratings .= "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
		} elseif ($p_ord > 959) { // On waivers
			$table_ratings .= "<td>(<a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name)*</a></td>";
		} elseif ($cy == $cyt) { // Eligible for FA this offseason
			$table_ratings .= "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a>^</td>";
		} else {
			$table_ratings .= "<td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
		}

		$table_ratings .= "<td>$age</td>
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
			<td>$inj</td>
		</tr>";

		$i++;
	}

	$table_ratings .= "</tbody></table>";

	if ($tid != 0) {
		$table_totals .= "<table align=\"center\" class=\"sortable\">
			<thead>
				<tr bgcolor=$color1>
					<th><font color=$color2>Pos</font></th>
					<th colspan=3><font color=$color2>Player</font></th>
					<th><font color=$color2>g</font></th>
					<th><font color=$color2>gs</font></th>
					<th><font color=$color2>min</font></th>
					<th><font color=$color2>fgm</font></th>
					<th><font color=$color2>fga</font></th>
					<th><font color=$color2>ftm</font></th>
					<th><font color=$color2>fta</font></th>
					<th><font color=$color2>3gm</font></th>
					<th><font color=$color2>3ga</font></th>
					<th><font color=$color2>orb</font></th>
					<th><font color=$color2>reb</font></th>
					<th><font color=$color2>ast</font></th>
					<th><font color=$color2>stl</font></th>
					<th><font color=$color2>to</font></th>
					<th><font color=$color2>blk</font></th>
					<th><font color=$color2>pf</font></th>
					<th><font color=$color2>pts</font></th>
				</tr>
			</thead>
		<tbody>";

		$i = 0;

		/* =======================STATS */

		while ($i < $num) {
			$name=mysql_result($result,$i,"name");
			$pos=mysql_result($result,$i,"pos");
			$p_ord=mysql_result($result,$i,"ordinal");
			$pid=mysql_result($result,$i,"pid");

			$draftyear=mysql_result($result,$i,"draftyear");
			$exp=mysql_result($result,$i,"exp");
			$cy=mysql_result($result,$i,"cy");
			$cyt=mysql_result($result,$i,"cyt");

			$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

			if ($yr == "") {
				$stats_gm=mysql_result($result,$i,"stats_gm");
				$stats_gs=mysql_result($result,$i,"stats_gs");
				$stats_min=mysql_result($result,$i,"stats_min");
				$stats_fgm=mysql_result($result,$i,"stats_fgm");
				$stats_fga=mysql_result($result,$i,"stats_fga");
				$stats_ftm=mysql_result($result,$i,"stats_ftm");
				$stats_fta=mysql_result($result,$i,"stats_fta");
				$stats_tgm=mysql_result($result,$i,"stats_3gm");
				$stats_tga=mysql_result($result,$i,"stats_3ga");
				$stats_orb=mysql_result($result,$i,"stats_orb");
				$stats_drb=mysql_result($result,$i,"stats_drb");
				$stats_ast=mysql_result($result,$i,"stats_ast");
				$stats_stl=mysql_result($result,$i,"stats_stl");
				$stats_to=mysql_result($result,$i,"stats_to");
				$stats_blk=mysql_result($result,$i,"stats_blk");
				$stats_pf=mysql_result($result,$i,"stats_pf");
				$stats_reb=$stats_orb+$stats_drb;
				$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;
			} else {
				$stats_gm=mysql_result($result,$i,"gm");
				$stats_min=mysql_result($result,$i,"min");
				$stats_fgm=mysql_result($result,$i,"fgm");
				$stats_fga=mysql_result($result,$i,"fga");
				$stats_ftm=mysql_result($result,$i,"ftm");
				$stats_fta=mysql_result($result,$i,"fta");
				$stats_tgm=mysql_result($result,$i,"3gm");
				$stats_tga=mysql_result($result,$i,"3ga");
				$stats_orb=mysql_result($result,$i,"orb");
				$stats_ast=mysql_result($result,$i,"ast");
				$stats_stl=mysql_result($result,$i,"stl");
				$stats_to=mysql_result($result,$i,"tvr");
				$stats_blk=mysql_result($result,$i,"blk");
				$stats_pf=mysql_result($result,$i,"pf");
				$stats_reb=mysql_result($result,$i,"reb");
				$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;
			}

			(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

			$table_totals .= "<tr bgcolor=$bgcolor>
				<td>$pos</td>";

			if ($tid == 0) {
				$table_totals .= "<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
			} elseif ($p_ord > 959) {
				$table_totals .= "<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td>";
			} elseif ($cy == $cyt) {
				$table_totals .= "<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name^</a></td>";
			} else {
				$table_totals .= "<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
			}

			$table_totals .= "<td><center>$stats_gm</center></td>
				<td><center>$stats_gs</center></td>
				<td><center>$stats_min</center></td>
				<td><center>$stats_fgm</center></td>
				<td><center>$stats_fga</center></td>
				<td><center>$stats_ftm</center></td>
				<td><center>$stats_fta</center></td>
				<td><center>$stats_tgm</center></td>
				<td><center>$stats_tga</center></td>
				<td><center>$stats_orb</center></td>
				<td><center>$stats_reb</center></td>
				<td><center>$stats_ast</center></td>
				<td><center>$stats_stl</center></td>
				<td><center>$stats_to</center></td>
				<td><center>$stats_blk</center></td>
				<td><center>$stats_pf</center></td>
				<td><center>$stats_pts</center></td>
			</tr>";

			$i++;
		}

		$table_totals .= "</tbody>
			<tfoot>";

		// ==== INSERT TEAM OFFENSE AND DEFENSE TOTALS ====

		$queryTeamOffenseTotals="SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
		$resultTeamOffenseTotals=mysql_query($queryTeamOffenseTotals);
		$numTeamOffenseTotals=mysql_numrows($resultTeamOffenseTotals);

		$t=0;

		while ($t < $numTeamOffenseTotals) {
			$team_off_games=mysql_result($resultTeamOffenseTotals,$t,"games");
			$team_off_minutes=mysql_result($resultTeamOffenseTotals,$t,"minutes");
			$team_off_fgm=mysql_result($resultTeamOffenseTotals,$t,"fgm");
			$team_off_fga=mysql_result($resultTeamOffenseTotals,$t,"fga");
			$team_off_ftm=mysql_result($resultTeamOffenseTotals,$t,"ftm");
			$team_off_fta=mysql_result($resultTeamOffenseTotals,$t,"fta");
			$team_off_tgm=mysql_result($resultTeamOffenseTotals,$t,"tgm");
			$team_off_tga=mysql_result($resultTeamOffenseTotals,$t,"tga");
			$team_off_orb=mysql_result($resultTeamOffenseTotals,$t,"orb");
			$team_off_reb=mysql_result($resultTeamOffenseTotals,$t,"reb");
			$team_off_ast=mysql_result($resultTeamOffenseTotals,$t,"ast");
			$team_off_stl=mysql_result($resultTeamOffenseTotals,$t,"stl");
			$team_off_tvr=mysql_result($resultTeamOffenseTotals,$t,"tvr");
			$team_off_blk=mysql_result($resultTeamOffenseTotals,$t,"blk");
			$team_off_pf=mysql_result($resultTeamOffenseTotals,$t,"pf");
			$team_off_pts=$team_off_fgm+$team_off_fgm+$team_off_ftm+$team_off_tgm;

			if ($yr == "") {
				$table_totals .= "<tr>
					<td colspan=4><b>$team_name Offense</td>
					<td><center><b>$team_off_games</center></td>
					<td><center><b>$team_off_games</center></td>
					<td><center><b>$team_off_minutes</center></td>
					<td><center><b>$team_off_fgm</center></td>
					<td><center><b>$team_off_fga</b></center></td>
					<td><center><b>$team_off_ftm</center></td>
					<td><center><b>$team_off_fta</b></center></td>
					<td><center><b>$team_off_tgm</center></td>
					<td><center><b>$team_off_tga</b></center></td>
					<td><center><b>$team_off_orb</center></td>
					<td><center><b>$team_off_reb</center></td>
					<td><center><b>$team_off_ast</center></td>
					<td><center><b>$team_off_stl</center></td>
					<td><center><b>$team_off_tvr</center></td>
					<td><center><b>$team_off_blk</center></td>
					<td><center><b>$team_off_pf</center></td>
					<td><center><b>$team_off_pts</center></td>
				</tr>";
			}
		$t++;
		}

		$queryTeamDefenseTotals="SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
		$resultTeamDefenseTotals=mysql_query($queryTeamDefenseTotals);
		$numTeamDefenseTotals=mysql_numrows($resultTeamDefenseTotals);

		$t=0;

		while ($t < $numTeamDefenseTotals) {
			$team_def_games=mysql_result($resultTeamDefenseTotals,$t,"games");
			$team_def_minutes=mysql_result($resultTeamDefenseTotals,$t,"minutes");
			$team_def_fgm=mysql_result($resultTeamDefenseTotals,$t,"fgm");
			$team_def_fga=mysql_result($resultTeamDefenseTotals,$t,"fga");
			$team_def_ftm=mysql_result($resultTeamDefenseTotals,$t,"ftm");
			$team_def_fta=mysql_result($resultTeamDefenseTotals,$t,"fta");
			$team_def_tgm=mysql_result($resultTeamDefenseTotals,$t,"tgm");
			$team_def_tga=mysql_result($resultTeamDefenseTotals,$t,"tga");
			$team_def_orb=mysql_result($resultTeamDefenseTotals,$t,"orb");
			$team_def_reb=mysql_result($resultTeamDefenseTotals,$t,"reb");
			$team_def_ast=mysql_result($resultTeamDefenseTotals,$t,"ast");
			$team_def_stl=mysql_result($resultTeamDefenseTotals,$t,"stl");
			$team_def_tvr=mysql_result($resultTeamDefenseTotals,$t,"tvr");
			$team_def_blk=mysql_result($resultTeamDefenseTotals,$t,"blk");
			$team_def_pf=mysql_result($resultTeamDefenseTotals,$t,"pf");
			$team_def_pts=$team_def_fgm+$team_def_fgm+$team_def_ftm+$team_def_tgm;

			if ($yr == "") {
				$table_totals .= "<tr>
					<td colspan=4><b>$team_name Defense</td>
					<td><center><b>$team_def_games</center></td>
					<td><center><b>$team_def_games</center></td>
					<td><center><b>$team_def_minutes</center></td>
					<td><center><b>$team_def_fgm</center></td>
					<td><center><b>$team_def_fga</b></center></td>
					<td><center><b>$team_def_ftm</center></td>
					<td><center><b>$team_def_fta</b></center></td>
					<td><center><b>$team_def_tgm</b></center></td>
					<td><center><b>$team_def_tga</b></center></td>
					<td><center><b>$team_def_orb</center></td>
					<td><center><b>$team_def_reb</center></td>
					<td><center><b>$team_def_ast</center></td>
					<td><center><b>$team_def_stl</center></td>
					<td><center><b>$team_def_tvr</center></td>
					<td><center><b>$team_def_blk</center></td>
					<td><center><b>$team_def_pf</center></td>
					<td><center><b>$team_def_pts</center></td>
				</tr>";
			}

			$t++;
		}

		$table_totals .= "</tfoot>
			</table>";

		$table_averages .= "<table align=\"center\" class=\"sortable\">
			<thead>
				<tr bgcolor=$color1>
					<th><font color=$color2>Pos</font></th>
					<th colspan=3><font color=$color2>Player</font></th>
					<th><font color=$color2>g</font></th>
					<th><font color=$color2>gs</font></th>
					<th><font color=$color2>min</font></th>
					<th><font color=$color2>fgm</font></th>
					<th><font color=$color2>fga</font></th>
					<th><font color=$color2>fgp</font></th>
					<th><font color=$color2>ftm</font></th>
					<th><font color=$color2>fta</font></th>
					<th><font color=$color2>ftp</font></th>
					<th><font color=$color2>3gm</font></th>
					<th><font color=$color2>3ga</font></th>
					<th><font color=$color2>3gp</font></th>
					<th><font color=$color2>orb</font></th>
					<th><font color=$color2>reb</font></th>
					<th><font color=$color2>ast</font></th>
					<th><font color=$color2>stl</font></th>
					<th><font color=$color2>to</font></th>
					<th><font color=$color2>blk</font></th>
					<th><font color=$color2>pf</font></th>
					<th><font color=$color2>pts</font></th>
				</tr>
			</thead>
		<tbody>";

		/* =======================AVERAGES */

		if ($yr == "") {
			if ($tid != "-1") {
				$per_query="SELECT * FROM ibl_per_calculator WHERE team_id = '$tid' ";
			} else {
				$per_query="SELECT * FROM ibl_per_calculator ";
			}
			$per_result=mysql_query($per_query);
			$per_rows=mysql_numrows($per_result);

			$per=0;
			while ($per < $per_rows) {
				$PER_array[$per]['name']=mysql_result($per_result,$per,"player_name");
				$PER_array[$per]['pid']=mysql_result($per_result,$per,"player_id");
				$PER_array[$per]['PER']=mysql_result($per_result,$per,"PER");
				$PER_array[$per]['MM']=mysql_result($per_result,$per,"Magic_Metric");
				$PER_array[$per]['GS']=mysql_result($per_result,$per,"Game_Score");

				$per++;
			}
		}
		$i=0;

		while ($i < $num) {
			$name=mysql_result($result,$i,"name");
			$pos=mysql_result($result,$i,"pos");
			$p_ord=mysql_result($result,$i,"ordinal");
			$pid=mysql_result($result,$i,"pid");

			$draftyear=mysql_result($result,$i,"draftyear");
			$exp=mysql_result($result,$i,"exp");
			$cy=mysql_result($result,$i,"cy");
			$cyt=mysql_result($result,$i,"cyt");

			$yearoffreeagency=$draftyear+$exp+$cyt-$cy;

			$stats_PER="";
			$stats_MM="";
			$stats_GS="";

			if ($yr == "") {
				$pc=0;
				while ($pc < $per) {
					if ($PER_array[$pc]['pid'] == $pid) {
						$stats_PER=number_format($PER_array[$pc]['PER'],3);
						$stats_Magic_Metric=number_format($PER_array[$pc]['MM'],3);
						$stats_Game_Score=number_format($PER_array[$pc]['GS'],3);
					}
					$pc++;
				}
				$stats_gm=mysql_result($result,$i,"stats_gm");
				$stats_gs=mysql_result($result,$i,"stats_gs");
				$stats_min=mysql_result($result,$i,"stats_min");
				$stats_fgm=mysql_result($result,$i,"stats_fgm");
				$stats_fga=mysql_result($result,$i,"stats_fga");
				$stats_ftm=mysql_result($result,$i,"stats_ftm");
				$stats_fta=mysql_result($result,$i,"stats_fta");
				$stats_tgm=mysql_result($result,$i,"stats_3gm");
				$stats_tga=mysql_result($result,$i,"stats_3ga");
				$stats_orb=mysql_result($result,$i,"stats_orb");
				$stats_drb=mysql_result($result,$i,"stats_drb");
				$stats_ast=mysql_result($result,$i,"stats_ast");
				$stats_stl=mysql_result($result,$i,"stats_stl");
				$stats_to=mysql_result($result,$i,"stats_to");
				$stats_blk=mysql_result($result,$i,"stats_blk");
				$stats_pf=mysql_result($result,$i,"stats_pf");
				$stats_reb=$stats_orb+$stats_drb;
				$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;
			} else {
				$stats_gm=mysql_result($result,$i,"gm");
				$stats_min=mysql_result($result,$i,"min");
				$stats_fgm=mysql_result($result,$i,"fgm");
				$stats_fga=mysql_result($result,$i,"fga");
				$stats_ftm=mysql_result($result,$i,"ftm");
				$stats_fta=mysql_result($result,$i,"fta");
				$stats_tgm=mysql_result($result,$i,"3gm");
				$stats_tga=mysql_result($result,$i,"3ga");
				$stats_orb=mysql_result($result,$i,"orb");
				$stats_ast=mysql_result($result,$i,"ast");
				$stats_stl=mysql_result($result,$i,"stl");
				$stats_to=mysql_result($result,$i,"tvr");
				$stats_blk=mysql_result($result,$i,"blk");
				$stats_pf=mysql_result($result,$i,"pf");
				$stats_reb=mysql_result($result,$i,"reb");
				$stats_pts=2*$stats_fgm+$stats_ftm+$stats_tgm;
			}
			@$stats_fgm=number_format(($stats_fgm/$stats_gm),1);
			@$stats_fga=number_format(($stats_fga/$stats_gm),1);
			@$stats_fgp=number_format(($stats_fgm/$stats_fga),3);
			@$stats_ftm=number_format(($stats_ftm/$stats_gm),1);
			@$stats_fta=number_format(($stats_fta/$stats_gm),1);
			@$stats_ftp=number_format(($stats_ftm/$stats_fta),3);
			@$stats_tgm=number_format(($stats_tgm/$stats_gm),1);
			@$stats_tga=number_format(($stats_tga/$stats_gm),1);
			@$stats_tgp=number_format(($stats_tgm/$stats_tga),3);
			@$stats_mpg=number_format(($stats_min/$stats_gm),1);
			@$stats_opg=number_format(($stats_orb/$stats_gm),1);
			@$stats_rpg=number_format(($stats_reb/$stats_gm),1);
			@$stats_apg=number_format(($stats_ast/$stats_gm),1);
			@$stats_spg=number_format(($stats_stl/$stats_gm),1);
			@$stats_tpg=number_format(($stats_to/$stats_gm),1);
			@$stats_bpg=number_format(($stats_blk/$stats_gm),1);
			@$stats_fpg=number_format(($stats_pf/$stats_gm),1);
			@$stats_ppg=number_format(($stats_pts/$stats_gm),1);

			(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

			$table_averages .= "<tr bgcolor=$bgcolor>
				<td>$pos</td>";

			if ($tid == 0) {
				$table_averages .= "<td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
			} else {
				if ($p_ord > 959) {
					$table_averages=$table_averages."<td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td>";
				} elseif ($cy == $cyt) {
					$table_averages=$table_averages."<td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name^</a></td>";
				}  else {
					$table_averages=$table_averages."<td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
				}
			}

			$table_averages .= "<td><center>$stats_gm</center></td>
				<td><center>$stats_gs</center></td>
				<td><center>$stats_mpg</center></td>
				<td><center>$stats_fgm</center></td>
				<td><center>$stats_fga</center></td>
				<td><center>$stats_fgp</center></td>
				<td><center>$stats_ftm</center></td>
				<td><center>$stats_fta</center></td>
				<td><center>$stats_ftp</center></td>
				<td><center>$stats_tgm</center></td>
				<td><center>$stats_tga</center></td>
				<td><center>$stats_tgp</center></td>
				<td><center>$stats_opg</center></td>
				<td><center>$stats_rpg</center></td>
				<td><center>$stats_apg</center></td>
				<td><center>$stats_spg</center></td>
				<td><center>$stats_tpg</center></td>
				<td><center>$stats_bpg</center></td>
				<td><center>$stats_fpg</center></td>
				<td><center>$stats_ppg</center></td>
			</tr>";

			$i++;
		}

		// ========= TEAM AVERAGES DISPLAY

		$table_averages=$table_averages."</tbody><tfoot>";

		$queryTeamOffenseTotals="SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
		$resultTeamOffenseTotals=mysql_query($queryTeamOffenseTotals);
		$numTeamOffenseTotals=mysql_numrows($resultTeamOffenseTotals);

		$t=0;

		while ($t < $numTeamOffenseTotals) {
			$team_off_games=mysql_result($resultTeamOffenseTotals,$t,"games");
			$team_off_minutes=mysql_result($resultTeamOffenseTotals,$t,"minutes");
			$team_off_fgm=mysql_result($resultTeamOffenseTotals,$t,"fgm");
			$team_off_fga=mysql_result($resultTeamOffenseTotals,$t,"fga");
			@$team_off_fgp=number_format(($team_off_fgm/$team_off_fga),3);
			$team_off_ftm=mysql_result($resultTeamOffenseTotals,$t,"ftm");
			$team_off_fta=mysql_result($resultTeamOffenseTotals,$t,"fta");
			@$team_off_ftp=number_format(($team_off_ftm/$team_off_fta),3);
			$team_off_tgm=mysql_result($resultTeamOffenseTotals,$t,"tgm");
			$team_off_tga=mysql_result($resultTeamOffenseTotals,$t,"tga");
			@$team_off_tgp=number_format(($team_off_tgm/$team_off_tga),3);
			$team_off_orb=mysql_result($resultTeamOffenseTotals,$t,"orb");
			$team_off_reb=mysql_result($resultTeamOffenseTotals,$t,"reb");
			$team_off_ast=mysql_result($resultTeamOffenseTotals,$t,"ast");
			$team_off_stl=mysql_result($resultTeamOffenseTotals,$t,"stl");
			$team_off_tvr=mysql_result($resultTeamOffenseTotals,$t,"tvr");
			$team_off_blk=mysql_result($resultTeamOffenseTotals,$t,"blk");
			$team_off_pf=mysql_result($resultTeamOffenseTotals,$t,"pf");
			$team_off_pts=$team_off_fgm+$team_off_fgm+$team_off_ftm+$team_off_tgm;

			@$team_off_avgfgm=number_format(($team_off_fgm/$team_off_games),1);
			@$team_off_avgfga=number_format(($team_off_fga/$team_off_games),1);
			@$team_off_avgftm=number_format(($team_off_ftm/$team_off_games),1);
			@$team_off_avgfta=number_format(($team_off_fta/$team_off_games),1);
			@$team_off_avgtgm=number_format(($team_off_tgm/$team_off_games),1);
			@$team_off_avgtga=number_format(($team_off_tga/$team_off_games),1);
			@$team_off_avgmin=number_format(($team_off_minutes/$team_off_games),1);
			@$team_off_avgorb=number_format(($team_off_orb/$team_off_games),1);
			@$team_off_avgreb=number_format(($team_off_reb/$team_off_games),1);
			@$team_off_avgast=number_format(($team_off_ast/$team_off_games),1);
			@$team_off_avgstl=number_format(($team_off_stl/$team_off_games),1);
			@$team_off_avgtvr=number_format(($team_off_tvr/$team_off_games),1);
			@$team_off_avgblk=number_format(($team_off_blk/$team_off_games),1);
			@$team_off_avgpf=number_format(($team_off_pf/$team_off_games),1);
			@$team_off_avgpts=number_format(($team_off_pts/$team_off_games),1);

			if ($yr == "") {
				$table_averages .= "<tr>
					<td colspan=4><b>$team_name Offense</td>
					<td><b><center>$team_off_games</center></td>
					<td><b><center>$team_off_games</center></td>
					<td><center><b>$team_off_avgmin</center></td>
					<td><center><b>$team_off_avgfgm</center></td>
					<td><center><b>$team_off_avgfga</center></td>
					<td><center><b>$team_off_fgp</center></td>
					<td><center><b>$team_off_avgftm</center></td>
					<td><center><b>$team_off_avgfta</center></td>
					<td><center><b>$team_off_ftp</center></td>
					<td><center><b>$team_off_avgtgm</center></td>
					<td><center><b>$team_off_avgtga</center></td>
					<td><center><b>$team_off_tgp</center></td>
					<td><center><b>$team_off_avgorb</center></td>
					<td><center><b>$team_off_avgreb</center></td>
					<td><center><b>$team_off_avgast</center></td>
					<td><center><b>$team_off_avgstl</center></td>
					<td><center><b>$team_off_avgtvr</center></td>
					<td><center><b>$team_off_avgblk</center></td>
					<td><center><b>$team_off_avgpf</center></td>
					<td><center><b>$team_off_avgpts</center></td>
				</tr>";
			}
			$t++;
		}

		$queryTeamDefenseTotals="SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
		$resultTeamDefenseTotals=mysql_query($queryTeamDefenseTotals);
		$numTeamDefenseTotals=mysql_numrows($resultTeamDefenseTotals);

		$t=0;

		while ($t < $numTeamDefenseTotals) {
			$team_def_games=mysql_result($resultTeamDefenseTotals,$t,"games");
			$team_def_minutes=mysql_result($resultTeamDefenseTotals,$t,"minutes");
			$team_def_fgm=mysql_result($resultTeamDefenseTotals,$t,"fgm");
			$team_def_fga=mysql_result($resultTeamDefenseTotals,$t,"fga");
			@$team_def_fgp=number_format(($team_def_fgm/$team_def_fga),3);
			$team_def_ftm=mysql_result($resultTeamDefenseTotals,$t,"ftm");
			$team_def_fta=mysql_result($resultTeamDefenseTotals,$t,"fta");
			@$team_def_ftp=number_format(($team_def_ftm/$team_def_fta),3);
			$team_def_tgm=mysql_result($resultTeamDefenseTotals,$t,"tgm");
			$team_def_tga=mysql_result($resultTeamDefenseTotals,$t,"tga");
			@$team_def_tgp=number_format(($team_def_tgm/$team_def_tga),3);
			$team_def_orb=mysql_result($resultTeamDefenseTotals,$t,"orb");
			$team_def_reb=mysql_result($resultTeamDefenseTotals,$t,"reb");
			$team_def_ast=mysql_result($resultTeamDefenseTotals,$t,"ast");
			$team_def_stl=mysql_result($resultTeamDefenseTotals,$t,"stl");
			$team_def_tvr=mysql_result($resultTeamDefenseTotals,$t,"tvr");
			$team_def_blk=mysql_result($resultTeamDefenseTotals,$t,"blk");
			$team_def_pf=mysql_result($resultTeamDefenseTotals,$t,"pf");
			$team_def_pts=$team_def_fgm+$team_def_fgm+$team_def_ftm+$team_def_tgm;

			@$team_def_avgfgm=number_format(($team_def_fgm/$team_def_games),1);
			@$team_def_avgfga=number_format(($team_def_fga/$team_def_games),1);
			@$team_def_avgftm=number_format(($team_def_ftm/$team_def_games),1);
			@$team_def_avgfta=number_format(($team_def_fta/$team_def_games),1);
			@$team_def_avgtgm=number_format(($team_def_tgm/$team_def_games),1);
			@$team_def_avgtga=number_format(($team_def_tga/$team_def_games),1);
			@$team_def_avgmin=number_format(($team_def_minutes/$team_def_games),1);
			@$team_def_avgorb=number_format(($team_def_orb/$team_def_games),1);
			@$team_def_avgreb=number_format(($team_def_reb/$team_def_games),1);
			@$team_def_avgast=number_format(($team_def_ast/$team_def_games),1);
			@$team_def_avgstl=number_format(($team_def_stl/$team_def_games),1);
			@$team_def_avgtvr=number_format(($team_def_tvr/$team_def_games),1);
			@$team_def_avgblk=number_format(($team_def_blk/$team_def_games),1);
			@$team_def_avgpf=number_format(($team_def_pf/$team_def_games),1);
			@$team_def_avgpts=number_format(($team_def_pts/$team_def_games),1);

			if ($yr == "") {
				$table_averages .= "<tr>
					<td colspan=4><b>$team_name Defense</td>
					<td><center><b>$team_def_games</center></td>
					<td><b>$team_def_games</td>
					<td><center><b>$team_def_avgmin</center></td>
					<td><center><b>$team_def_avgfgm</center></td>
					<td><center><b>$team_def_avgfga</center></td>
					<td><center><b>$team_def_fgp</center></td>
					<td><center><b>$team_def_avgftm</center></td>
					<td><center><b>$team_def_avgfta</center></td>
					<td><center><b>$team_def_ftp</center></td>
					<td><center><b>$team_def_avgtgm</center></td>
					<td><center><b>$team_def_avgtga</center></td>
					<td><center><b>$team_def_tgp</center></td>
					<td><center><b>$team_def_avgorb</center></td>
					<td><center><b>$team_def_avgreb</center></td>
					<td><center><b>$team_def_avgast</center></td>
					<td><center><b>$team_def_avgstl</center></td>
					<td><center><b>$team_def_avgtvr</center></td>
					<td><center><b>$team_def_avgblk</center></td>
					<td><center><b>$team_def_avgpf</center></td>
					<td><center><b>$team_def_avgpts</center></td>
				</tr>";
			}
		$t++;
		}

		$thischunk = $row[maxchunk];

		$table_averages .= "</tfoot>
			</table>";

		if ($yr == "") {
			$table_simAverages=$table_simAverages."<table align=\"center\" class=\"sortable\"><thead>";

			/* ======================CHUNK STATS */

			$table_simAverages=$table_simAverages."<tr bgcolor=$color1><th><font color=$color2>Pos</font></th><th colspan=3><font color=$color2>Player</font></th><th><font color=$color2>g</font></th><th><font color=$color2>min</font></th><th><font color=$color2>fgm</font></th><th><font color=$color2>fga</font></th><th><font color=$color2>fgp</font></th><th><font color=$color2>ftm</font></th><th><font color=$color2>fta</font></th><th><font color=$color2>ftp</font></th><th><font color=$color2>3gm</font></th><th><font color=$color2>3ga</font></th><th><font color=$color2>3gp</font></th><th><font color=$color2>orb</font></th><th><font color=$color2>reb</font></th><th><font color=$color2>ast</font></th><th><font color=$color2>stl</font></th><th><font color=$color2>to</font></th><th><font color=$color2>blk</font></th><th><font color=$color2>pf</font></th><th><font color=$color2>pts</font></th></tr></thead><tbody>";

			$arrayLastSimDates = Shared::getLastSimDatesArray();

			$simStartDate = $arrayLastSimDates['Start Date'];
			$simEndDate = $arrayLastSimDates['End Date'];

			$playersOnTeam = mysql_query("SELECT pid
		        FROM nuke_iblplyr
		        WHERE tid = $tid
		        ORDER BY name ASC");
			$numberOfPlayersOnTeam = mysql_num_rows($playersOnTeam);

			$i = 0;
			while ($i < $numberOfPlayersOnTeam) {
				$pid = mysql_result($playersOnTeam, $i);

				// TODO: refactor this so that I'm not cutting and pasting the Player module's Sim Stats code
				$resultPlayerSimBoxScores = $db->sql_query("SELECT *
		            FROM ibl_box_scores
		            WHERE pid = $pid
		            AND Date BETWEEN '$simStartDate' AND '$simEndDate'
					AND gameMIN > 0
		            ORDER BY Date ASC");

		        $numberOfGamesPlayedInSim = mysql_num_rows($resultPlayerSimBoxScores);
				$simTotalMIN = 0;
				$simTotal2GM = 0;
				$simTotal2GA = 0;
				$simTotalFTM = 0;
				$simTotalFTA = 0;
				$simTotal3GM = 0;
				$simTotal3GA = 0;
				$simTotalORB = 0;
				$simTotalDRB = 0;
				$simTotalAST = 0;
				$simTotalSTL = 0;
				$simTotalTOV = 0;
				$simTotalBLK = 0;
				$simTotalPF = 0;
				$simTotalPTS = 0;

				if ($numberOfGamesPlayedInSim > 0) {
			        while ($row = mysql_fetch_assoc($resultPlayerSimBoxScores)) {
						$name = $row['name'];
						$pos = $row['pos'];

			            $simTotalMIN += $row['gameMIN'];
			            $simTotal2GM += $row['game2GM'];
			            $simTotal2GA += $row['game2GA'];
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
			            $simTotalPTS += (2 * $row['game2GM']) + $row['gameFTM'] + (3 * $row['game3GM']);
			        }

			        @$simAverageMIN = number_format(($simTotalMIN / $numberOfGamesPlayedInSim),1);
			        @$simAverage2GM = $simTotal2GM / $numberOfGamesPlayedInSim;
			        @$simAverage2GA = $simTotal2GA / $numberOfGamesPlayedInSim;
			        @$simAverage2GP = number_format(($simTotal2GM / $simTotal2GA),3);
			        @$simAverageFTM = number_format(($simTotalFTM / $numberOfGamesPlayedInSim),1);
			        @$simAverageFTA = number_format(($simTotalFTA / $numberOfGamesPlayedInSim),1);
			        @$simAverageFTP = number_format(($simTotalFTM / $simTotalFTA),3);
			        @$simAverage3GM = number_format(($simTotal3GM / $numberOfGamesPlayedInSim),1);
			        @$simAverage3GA = number_format(($simTotal3GA / $numberOfGamesPlayedInSim),1);
			        @$simAverage3GP = number_format(($simTotal3GM / $simTotal3GA),3);
			        @$simAverageFGM = number_format((($simTotal2GM + $simTotal3GM) / $numberOfGamesPlayedInSim),1);
			        @$simAverageFGA = number_format((($simTotal2GA + $simTotal3GA) / $numberOfGamesPlayedInSim),1);
			        @$simAverageFGP = number_format((($simTotal2GM + $simTotal3GM) / ($simTotal2GA + $simTotal3GA)),3);
			        @$simAverageORB = number_format(($simTotalORB / $numberOfGamesPlayedInSim),1);
			        @$simAverageDRB = number_format(($simTotalDRB / $numberOfGamesPlayedInSim),1);
			        @$simAverageREB = number_format((($simTotalORB + $simTotalDRB) / $numberOfGamesPlayedInSim),1);
			        @$simAverageAST = number_format(($simTotalAST / $numberOfGamesPlayedInSim),1);
			        @$simAverageSTL = number_format(($simTotalSTL / $numberOfGamesPlayedInSim),1);
			        @$simAverageTOV = number_format(($simTotalTOV / $numberOfGamesPlayedInSim),1);
			        @$simAverageBLK = number_format(($simTotalBLK / $numberOfGamesPlayedInSim),1);
			        @$simAveragePF = number_format(($simTotalPF / $numberOfGamesPlayedInSim),1);
			        @$simAveragePTS = number_format(($simTotalPTS / $numberOfGamesPlayedInSim),1);

					(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

					$table_simAverages .= "<tr bgcolor=$bgcolor>
						<td>$pos</td>
						<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
						<td><center>$numberOfGamesPlayedInSim</center></td>
						<td><center>$simAverageMIN</center></td>
						<td><center>$simAverageFGM</center></td>
						<td><center>$simAverageFGA</center></td>
						<td><center>$simAverageFGP</center></td>
						<td><center>$simAverageFTM</center></td>
						<td><center>$simAverageFTA</center></td>
						<td><center>$simAverageFTP</center></td>
						<td><center>$simAverage3GM</center></td>
						<td><center>$simAverage3GA</center></td>
						<td><center>$simAverage3GP</center></td>
						<td><center>$simAverageORB</center></td>
						<td><center>$simAverageREB</center></td>
						<td><center>$simAverageAST</center></td>
						<td><center>$simAverageSTL</center></td>
						<td><center>$simAverageTOV</center></td>
						<td><center>$simAverageBLK</center></td>
						<td><center>$simAveragePF</center></td>
						<td><center>$simAveragePTS</center></td>
					</tr>";
				}

				$i++;
			}

			$table_simAverages .= "</tbody>
				</table>";
		} // END OF IF $yr == "" BRACE TO REMOVE PER CHUNK STUFF
	} // END OF TID != 0 brace - inserted so that Free Agents won't clog up the page with season averages and totals when those are almost always zeros.

	if ($yr == "") {
		$table_contracts=$table_contracts."<table align=\"center\" class=\"sortable\">
			<thead>
				<tr bgcolor=$color1>
					<th><font color=$color2>Pos</font></th>
					<th colspan=3><font color=$color2>Player</font></th>
					<th><font color=$color2>Bird</font></th>
					<th><font color=$color2>Year1</font></th>
					<th><font color=$color2>Year2</font></th>
					<th><font color=$color2>Year3</font></th>
					<th><font color=$color2>Year4</font></th>
					<th><font color=$color2>Year5</font></th>
					<th><font color=$color2>Year6</font></th><td bgcolor=#000000 width=3></th>
					<th><font color=$color2>Talent</font></th>
					<th><font color=$color2>Skill</font></th>
					<th><font color=$color2>Intang</font></th>
					<th><font color=$color2>Clutch</font></th>
					<th><font color=$color2>Consistency</font></th>
				</tr>
			</thead>
		<tbody>";

		/* =======================CONTRACTS ET AL */

		$i = 0;
		while ($i < $num) {
			$name=mysql_result($result,$i,"name");
			$pos=mysql_result($result,$i,"pos");
			$p_ord=mysql_result($result,$i,"ordinal");
			$pid=mysql_result($result,$i,"pid");
			$cy=mysql_result($result,$i,"cy");
			$cyt=mysql_result($result,$i,"cyt");
			if ($faon == 0) {
				$year1=$cy;
				$year2=$cy+1;
				$year3=$cy+2;
				$year4=$cy+3;
				$year5=$cy+4;
				$year6=$cy+5;
			} else {
				$year1=$cy+1;
				$year2=$cy+2;
				$year3=$cy+3;
				$year4=$cy+4;
				$year5=$cy+5;
				$year6=$cy+6;
			}
			if ($cy==0) {
				$year1 < 7 ? $con1=mysql_result($result,$i,"cy1") : $con1=0;
				$year2 < 7 ? $con2=mysql_result($result,$i,"cy2") : $con2=0;
				$year3 < 7 ? $con3=mysql_result($result,$i,"cy3") : $con3=0;
				$year4 < 7 ? $con4=mysql_result($result,$i,"cy4") : $con4=0;
				$year5 < 7 ? $con5=mysql_result($result,$i,"cy5") : $con5=0;
				$year6 < 7 ? $con6=mysql_result($result,$i,"cy6") : $con6=0;
			} else {
				$year1 < 7 ? $con1=mysql_result($result,$i,"cy$year1") : $con1=0;
				$year2 < 7 ? $con2=mysql_result($result,$i,"cy$year2") : $con2=0;
				$year3 < 7 ? $con3=mysql_result($result,$i,"cy$year3") : $con3=0;
				$year4 < 7 ? $con4=mysql_result($result,$i,"cy$year4") : $con4=0;
				$year5 < 7 ? $con5=mysql_result($result,$i,"cy$year5") : $con5=0;
				$year6 < 7 ? $con6=mysql_result($result,$i,"cy$year6") : $con6=0;
			}
			$bird=mysql_result($result,$i,"bird");
			$talent=mysql_result($result,$i,"talent");
			$skill=mysql_result($result,$i,"skill");
			$intangibles=mysql_result($result,$i,"intangibles");
			$Clutch=mysql_result($result,$i,"Clutch");
			$Consistency=mysql_result($result,$i,"Consistency");

			(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="EEEEEE";

			$table_contracts .= "<tr bgcolor=$bgcolor><td>$pos</td>";

			if ($tid == 0) {
				$table_contracts=$table_contracts."<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
			} elseif ($p_ord > 959) {
				$table_contracts=$table_contracts."<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">($name)*</a></td>";
			} elseif ($cy == $cyt) {
				$table_contracts=$table_contracts."<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name^</a></td>";
			} else {
				$table_contracts=$table_contracts."<td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>";
			}

			$table_contracts .= "<td>$bird</td>
				<td>$con1</td>
				<td>$con2</td>
				<td>$con3</td>
				<td>$con4</td>
				<td>$con5</td>
				<td>$con6</td><td bgcolor=#000000></td>
				<td>$talent</td>
				<td>$skill</td>
				<td>$intangibles</td>
				<td>$Clutch</td>
				<td>$Consistency</td>
			</tr>";

			$cap1=$cap1+$con1;
			$cap2=$cap2+$con2;
			$cap3=$cap3+$con3;
			$cap4=$cap4+$con4;
			$cap5=$cap5+$con5;
			$cap6=$cap6+$con6;
			$i++;
		}
		$cap1=number_format($cap1/100,2);
		$cap2=number_format($cap2/100,2);
		$cap3=number_format($cap3/100,2);
		$cap4=number_format($cap4/100,2);
		$cap5=number_format($cap5/100,2);
		$cap6=number_format($cap6/100,2);

		// Begin hack to populate a MySQL table that has each team's current cap total.
		// Calculating cap totals for the current season is dificult at the moment. - A-Jay
			$currentCap = $cap1;
			$capTotalQuery = "INSERT INTO ibl_current_cap (tid,currentCap) VALUES ('".$tid."','".$currentCap."') ON DUPLICATE KEY UPDATE currentCap='".$currentCap."'";
			$capTotalQueryExec = mysql_query($capTotalQuery);
		// End salary cap hack.

		$table_contracts .= "</tbody>
			<tfoot>
				<tr>
					<td></td>
					<td colspan=3><b>Cap Totals</td>
					<td></td>
					<td><b>$cap1</td>
					<td><b>$cap2</td>
					<td><b>$cap3</td>
					<td><b>$cap4</td>
					<td><b>$cap5</td>
					<td><b>$cap6</td><td bgcolor=#000000></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td colspan=17><i>Note:</i> Players whose names appear in parenthesis and with a trailing asterisk are waived players that still count against the salary cap.</td>
				</tr>
			</tfoot>
		</table>";

	} // END OF IF YEAR EQUAL NULL STATEMENT FROM BEFORE CHUNK AVERAGES

	$table_draftpicks=$table_draftpicks."<table align=\"center\">";

	// PUT DRAFT PICKS BELOW SALARY PRINTOUT

	$querypicks="SELECT * FROM ibl_draft_picks WHERE ownerofpick = '$team_name' ORDER BY year, round ASC";
	$resultpicks=mysql_query($querypicks);
	$numpicks=mysql_num_rows($resultpicks);

	$hh=0;

	$query_all_team_colors="SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC";
	$colors=mysql_query($query_all_team_colors);
	$num_all_team_colors=mysql_num_rows($colors);

	$i=0;
	while ($i < $num_all_team_colors) {
		$color_array[$i]['team_id']=mysql_result($colors,$i,"teamid");
		$color_array[$i]['team_city']=mysql_result($colors,$i,"team_city");
		$color_array[$i]['team_name']=mysql_result($colors,$i,"team_name");
		$color_array[$i]['color1']=mysql_result($colors,$i,"color1");
		$color_array[$i]['color2']=mysql_result($colors,$i,"color2");
		$i++;
	}

	while ($hh < $numpicks)	{
		$ownerofpick=mysql_result($resultpicks,$hh,"ownerofpick");
		$teampick=mysql_result($resultpicks,$hh,"teampick");
		$year=mysql_result($resultpicks,$hh,"year");
		$round=mysql_result($resultpicks,$hh,"round");

		$j=0;
			while ($j < $i) {
			$pick_team_name=$color_array[$j]['team_name'];
			if ($pick_team_name == $teampick) {
				$pick_team_id=$color_array[$j]['team_id'];
				$pick_team_city=$color_array[$j]['team_city'];
				$pick_team_color1=$color_array[$j]['color1'];
				$pick_team_color2=$color_array[$j]['color2'];
			}
			$j++;
		}
		$table_draftpicks=$table_draftpicks."<tr><td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\"><img src=\"images/logo/$teampick.png\"></a></td><td valign=\"center\"><a href=\"modules.php?name=Team&op=team&tid=$pick_team_id\">$year $pick_team_city $teampick (Round $round)</a></td></tr>";

		$hh++;
	}
	$table_draftpicks=$table_draftpicks."</table>";

	$inforight = team_info_right ($team_name, $color1, $color2, $owner_name, $tid);

	$team_info_right=$inforight[0];
	$rafters=$inforight[1];

	if ($yr != "") {
		$insertyear="&yr=$yr";
	} else {
		$insertyear="";
	}

	if ($display == "ratings") {
		$showing="Player Ratings";
		$tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a></td>";
		$table_output=$table_ratings;
	} else $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=ratings$insertyear\">Ratings</a></td>";
	if ($display == "total_s") {
		$showing="Season Totals";
		$tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a></td>";
		$table_output=$table_totals;
	} else $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=total_s$insertyear\">Season Totals</a></td>";
	if ($display == "avg_s") {
		$showing="Season Averages";
		$tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a></td>";
		$table_output=$table_averages;
	} else $tabs=$tabs."<td ><a href=\"modules.php?name=Team&op=team&tid=$tid&display=avg_s$insertyear\">Season Averages</a></td>";
	if ($display == "chunk") {
		$showing="Chunk Averages";
		$tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a></td>";
		$table_output=$table_simAverages;
	} else $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=chunk$insertyear\">Sim Averages</a></td>";
	if ($display == "contracts") {
		$showing="Contracts";
		$tabs=$tabs."<td bgcolor=#BBBBBB><a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";
		$table_output=$table_contracts;
	} else $tabs=$tabs."<td><a href=\"modules.php?name=Team&op=team&tid=$tid&display=contracts$insertyear\">Contracts</a></td>";

	echo "<table align=center><tr bgcolor=$color1><td><font color=$color2><b><center>$showing (Sortable by clicking on Column Heading)</center></b></font></td></tr>
		<tr><td align=center><table><tr>$tabs</tr></table></td></tr>
		<tr><td align=center>$table_output</td></tr>
		<tr><td align=center>$starters_table</td></tr>
		<tr bgcolor=$color1><td><font color=$color2><b><center>Draft Picks</center></b></font></td></tr>
		<tr><td>$table_draftpicks</td></tr>
		<tr><td>$rafters</td></tr></table>";

	// TRANSITIONS TO NEXT SIDE OF PAGE

	echo "</td><td valign=top>$team_info_right</td></tr></table>";

	// CLOSES UP THE TABLE

	CloseTable();
	include("footer.php");
}

function team_info_right ($team_name, $color1, $color2, $owner_name, $tid)
{
	// ==== GET OWNER INFO

	$queryo="SELECT * FROM nuke_users WHERE user_ibl_team = '$team_name' ORDER BY user_id DESC";
	$resulto=mysql_query($queryo);
	$numo=mysql_num_rows($resulto);

	$query1="SELECT * FROM nuke_ibl_team_info WHERE teamid = $tid";
	$result1=mysql_query($query1);

	$coaching=mysql_result($result1,0,"Contract_Coach");

	$user_id=mysql_result($resulto,0,"user_id");
	$username=mysql_result($resulto,0,"username");
	$user_lastvisit=mysql_result($resulto,0,"user_lastvisit");
	$date_started=mysql_result($resulto,0,"date_started");
	$visitdate=date(r,$user_lastvisit);

	$output="<table bgcolor=#eeeeee width=220>";
	$output=$output."<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>Current Season</b></font>
		</td></tr>
		<tr><td>";
	$output=$output.standings($team_name);
	$output=$output."</td></tr>";

	//==================
	// GM HISTORY
	//==================

	$owner_award_code = $owner_name." (".$team_name.")";
	$querydec="SELECT * FROM nuke_ibl_gmhistory WHERE name LIKE '$owner_award_code' ORDER BY year ASC";
	$resultdec=mysql_query($querydec);
	$numdec=mysql_num_rows($resultdec);
	if ($numdec > 0) $dec=0;
	$output=$output."<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>GM History</b></font>
		</td></tr>
		<tr><td>";
	while ($dec < $numdec) {
		$dec_year=mysql_result($resultdec,$dec,"year");
		$dec_Award=mysql_result($resultdec,$dec,"Award");
		$output=$output."<table border=0 cellpadding=0 cellspacing=0><tr><td>$dec_year $dec_Award</td></tr></table>";
		$dec++;
	}
	$output=$output."</td></tr>";

	// CHAMPIONSHIP BANNERS

	$querybanner="SELECT * FROM nuke_iblbanners WHERE currentname = '$team_name' ORDER BY year ASC";
	$resultbanner=mysql_query($querybanner);
	$numbanner=mysql_num_rows($resultbanner);

	$j=0;

	$championships=0;
	$conference_titles=0;
	$division_titles=0;

	$champ_text="";
	$conf_text="";
	$div_text="";

	$ibl_banner="";
	$conf_banner="";
	$div_banner="";

	while ($j < $numbanner)	{
		$banneryear=mysql_result($resultbanner,$j,"year");
		$bannername=mysql_result($resultbanner,$j,"bannername");
		$bannertype=mysql_result($resultbanner,$j,"bannertype");

		if ($bannertype == 1) {
			if ($championships % 5 == 0) {
				$ibl_banner=$ibl_banner."<tr><td align=\"center\"><table><tr>";
			}
			$ibl_banner=$ibl_banner."<td><table><tr bgcolor=$color1><td valign=top height=80 width=120 background=\"./images/banners/banner1.gif\"><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>IBL Champions</b></center></td></tr></table></td>";

			$championships++;

			if ($championships % 5 == 0) {
				$ibl_banner=$ibl_banner."</tr></td></table></tr>";
			}

			if ($champ_text == "") {
				$champ_text="$banneryear";
			} else {
				$champ_text=$champ_text.", $banneryear";
			}
			if ($bannername != $team_name) {
				$champ_text=$champ_text." (as $bannername)";
			}
		} else if ($bannertype == 2 OR $bannertype == 3) {
			if ($conference_titles % 5 == 0) {
				$conf_banner=$conf_banner."<tr><td align=\"center\"><table><tr>";
			}

			$conf_banner=$conf_banner."<td><table><tr bgcolor=$color1><td valign=top height=80 width=120 background=\"./images/banners/banner2.gif\"><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>";
			if ($bannertype == 2) {
				$conf_banner=$conf_banner."Eastern Conf. Champions</b></center></td></tr></table></td>";
			} else {
				$conf_banner=$conf_banner."Western Conf. Champions</b></center></td></tr></table></td>";
			}

			$conference_titles++;

			if ($conference_titles % 5 == 0) {
				$conf_banner=$conf_banner."</tr></table></td></tr>";
			}

			if ($conf_text == "") {
				$conf_text="$banneryear";
			} else {
				$conf_text=$conf_text.", $banneryear";
			}
			if ($bannername != $team_name) {
				$conf_text=$conf_text." (as $bannername)";
			}
		} else if ($bannertype == 4 OR $bannertype == 5 OR $bannertype == 6 OR $bannertype == 7) {
			if ($division_titles % 5 == 0) {
				$div_banner=$div_banner."<tr><td align=\"center\"><table><tr>";
			}
			$div_banner=$div_banner."<td><table><tr bgcolor=$color1><td valign=top height=80 width=120><font color=#$color2>
				<center><b>$banneryear<br>
				$bannername<br>";
			if ($bannertype == 4) {
				$div_banner=$div_banner."Atlantic Div. Champions</b></center></td></tr></table></td>";
			} else if ($bannertype == 5) {
				$div_banner=$div_banner."Central Div. Champions</b></center></td></tr></table></td>";
			} else if ($bannertype == 6) {
				$div_banner=$div_banner."Midwest Div. Champions</b></center></td></tr></table></td>";
			} else if ($bannertype == 7) {
				$div_banner=$div_banner."Pacific Div. Champions</b></center></td></tr></table></td>";
			}

			$division_titles++;

			if ($division_titles % 5 == 0) {
				$div_banner=$div_banner."</tr></table></td></tr>";
			}

			if ($div_text == "") {
				$div_text="$banneryear";
			} else {
				$div_text=$div_text.", $banneryear";
			}
			if ($bannername != $team_name) {
				$div_text=$div_text." (as $bannername)";
			}
		}
		$j++;
	}

	if (substr($ibl_banner, -23) != "</tr></table></td></tr>" AND $ibl_banner != "" ) {
		$ibl_banner=$ibl_banner."</tr></table></td></tr>";
	}
	if (substr($conf_banner, -23) != "</tr></table></td></tr>" AND $conf_banner != "" ) {
		$conf_banner=$conf_banner."</tr></table></td></tr>";
	}
	if (substr($div_banner, -23) != "</tr></table></td></tr>"  AND $div_banner != "" ) {
		$div_banner=$div_banner."</tr></table></td></tr>";
	}

	$banner_output="";
	if ($ibl_banner != "") {
		$banner_output=$banner_output.$ibl_banner;
	}
	if ($conf_banner != "") {
		$banner_output=$banner_output.$conf_banner;
	}
	if ($div_banner != "") {
		$banner_output=$banner_output.$div_banner;
	}
	if ($banner_output != "") {
		$banner_output="<center><table><tr><td bgcolor=\"#$color1\" align=\"center\"><font color=\"#$color2\"><h2>$team_name Banners</h2></font></td></tr>".$banner_output."</table></center>";
	}

	$ultimate_output[1]=$banner_output;

	/*

	$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"<b>Team Banners</b></font></td></tr>
	<tr><td>$championships IBL Championships: $champ_text</td></tr>
	<tr><td>$conference_titles Conference Championships: $conf_text</td></tr>
	<tr><td>$division_titles Division Titles: $div_text</td></tr>
	";

	*/

	//==================
	// TEAM ACCOMPLISHMENTS
	//==================

	$owner_award_code = $team_name."";
	$querydec="SELECT * FROM nuke_ibl_teamawards WHERE name LIKE '$owner_award_code' ORDER BY year DESC";
	$resultdec=mysql_query($querydec);
	$numdec=mysql_num_rows($resultdec);
	if ($numdec > 0) $dec=0;
	$output=$output."<tr bgcolor=\"#$color1\"><td align=\"center\">
		<font color=\"#$color2\"><b>Team Accomplishments</b></font>
		</td></tr>
		<tr><td>";
	while ($dec < $numdec) {
		$dec_year=mysql_result($resultdec,$dec,"year");
		$dec_Award=mysql_result($resultdec,$dec,"Award");
		$output=$output."<table border=0 cellpadding=0 cellspacing=0><tr><td>$dec_year $dec_Award</td></tr></table>";
		$dec++;
	}
	$output=$output."</td></tr>";

	// REGULAR SEASON RESULTS

	$querywl="SELECT * FROM nuke_iblteam_win_loss WHERE currentname = '$team_name' ORDER BY year DESC";
	$resultwl=mysql_query($querywl);
	$numwl=mysql_num_rows($resultwl);

	$h=0;
	$wintot=0;
	$lostot=0;

	$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Regular Season History</b></font></td></tr>
		<tr><td><div id=\"History-R\" style=\"overflow:auto\">";

	while ($h < $numwl) {
		$yearwl=mysql_result($resultwl,$h,"year");
		$namewl=mysql_result($resultwl,$h,"namethatyear");
		$wins=mysql_result($resultwl,$h,"wins");
		$losses=mysql_result($resultwl,$h,"losses");
		$wintot=$wintot+$wins;
		$lostot=$lostot+$losses;
		@$winpct=number_format($wins/($wins+$losses),3);
		$output=$output."<a href=\"./modules.php?name=Team&op=team&tid=$tid&yr=$yearwl\">" . ($yearwl - 1) . "-$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

		$h++;
	}
	@$wlpct=number_format($wintot/($wintot+$lostot),3);

	$output=$output."</div></td></tr>
		<tr><td><b>Totals:</b> $wintot - $lostot ($wlpct)</td></tr>";

	// HEAT SEASON RESULTS

	$querywl="SELECT * FROM nuke_heat_win_loss WHERE currentname = '$team_name' ORDER BY year DESC";
	$resultwl=mysql_query($querywl);
	$numwl=mysql_num_rows($resultwl);
	$h=0;
	$wintot=0;
	$lostot=0;

	$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>H.E.A.T. History</b></font></td></tr>
		<tr><td><div id=\"History-R\" style=\"overflow:auto\">";

	while ($h < $numwl)	{
		$yearwl=mysql_result($resultwl,$h,"year");
		$namewl=mysql_result($resultwl,$h,"namethatyear");
		$wins=mysql_result($resultwl,$h,"wins");
		$losses=mysql_result($resultwl,$h,"losses");
		$wintot=$wintot+$wins;
		$lostot=$lostot+$losses;
		@$winpct=number_format($wins/($wins+$losses),3);
		$output=$output."<a href=\"./modules.php?name=Team&op=team&tid=$tid&yr=$yearwl\">$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

		$h++;
	}
	@$wlpct=number_format($wintot/($wintot+$lostot),3);

	$output=$output."</div></td></tr>
		<tr><td><b>Totals:</b> $wintot - $lostot ($wlpct)</td></tr>";

	// POST-SEASON RESULTS

	$queryplayoffs = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
	$resultplayoffs = mysql_query($queryplayoffs);
	$numplayoffs = mysql_num_rows($resultplayoffs);

	$pp = 0;
	$totalplayoffwins = 0;
	$totalplayofflosses = 0;
	$first_round_victories = 0;
	$second_round_victories = 0;
	$third_round_victories = 0;
	$fourth_round_victories = 0;
	$first_round_losses = 0;
	$second_round_losses = 0;
	$third_round_losses = 0;
	$fourth_round_losses = 0;

	$round_one_output = "";
	$round_two_output = "";
	$round_three_output = "";
	$round_four_output = "";

	$first_wins = 0;
	$second_wins = 0;
	$third_wins = 0;
	$fourth_wins = 0;

	while ($pp < $numplayoffs) {
		$playoffround = mysql_result($resultplayoffs,$pp,"round");
		$playoffyear = mysql_result($resultplayoffs,$pp,"year");
		$playoffwinner = mysql_result($resultplayoffs,$pp,"winner");
		$playoffloser = mysql_result($resultplayoffs,$pp,"loser");
		$playoffloser_games = mysql_result($resultplayoffs,$pp,"loser_games");

		if ($playoffround == 1) {
			if ($playoffwinner == $team_name) {
				$totalplayoffwins = $totalplayoffwins + 4;
				$totalplayofflosses = $totalplayofflosses + $playoffloser_games;
				$first_wins = $first_wins + 4;
				$first_losses = $first_losses + $playoffloser_games;
				$first_round_victories++;
				$round_one_output = $round_one_output . "$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
			} else if ($playoffloser == $team_name) {
				$totalplayofflosses = $totalplayofflosses + 4;
				$totalplayoffwins = $totalplayoffwins + $playoffloser_games;
				$first_losses = $first_losses + 4;
				$first_wins = $first_wins + $playoffloser_games;
				$first_round_losses++;
				$round_one_output = $round_one_output . "$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
			}
		} else if ($playoffround == 2) {
			if ($playoffwinner == $team_name) {
				$totalplayoffwins=$totalplayoffwins+4;
				$totalplayofflosses=$totalplayofflosses+$playoffloser_games;
				$second_wins=$second_wins+4;
				$second_losses=$second_losses+$playoffloser_games;
				$second_round_victories++;
				$round_two_output=$round_two_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
			} else if ($playoffloser == $team_name) {
				$totalplayofflosses=$totalplayofflosses+4;
				$totalplayoffwins=$totalplayoffwins+$playoffloser_games;
				$second_losses=$second_losses+4;
				$second_wins=$second_wins+$playoffloser_games;
				$second_round_losses++;
				$round_two_output=$round_two_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
			}
		} else if ($playoffround == 3) {
			if ($playoffwinner == $team_name) {
				$totalplayoffwins=$totalplayoffwins+4;
				$totalplayofflosses=$totalplayofflosses+$playoffloser_games;
				$third_wins=$third_wins+4;
				$third_losses=$third_losses+$playoffloser_games;
				$third_round_victories++;
				$round_three_output=$round_three_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
			} else if ($playoffloser == $team_name) {
				$totalplayofflosses=$totalplayofflosses+4;
				$totalplayoffwins=$totalplayoffwins+$playoffloser_games;
				$third_losses=$third_losses+4;
				$third_wins=$third_wins+$playoffloser_games;
				$third_round_losses++;
				$round_three_output=$round_three_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
			}
		} else if ($playoffround == 4) {
			if ($playoffwinner == $team_name) {
				$totalplayoffwins=$totalplayoffwins+4;
				$totalplayofflosses=$totalplayofflosses+$playoffloser_games;
				$fourth_wins=$fourth_wins+4;
				$fourth_losses=$fourth_losses+$playoffloser_games;
				$fourth_round_victories++;
				$round_four_output=$round_four_output."$playoffyear - $team_name 4, $playoffloser $playoffloser_games<br>";
			} else if ($playoffloser == $team_name) {
				$totalplayofflosses=$totalplayofflosses+4;
				$totalplayoffwins=$totalplayoffwins+$playoffloser_games;
				$fourth_losses=$fourth_losses+4;
				$fourth_wins=$fourth_wins+$playoffloser_games;
				$fourth_round_losses++;
				$round_four_output=$round_four_output."$playoffyear - $playoffwinner 4, $team_name $playoffloser_games<br>";
			}
		}
		$pp++;
	}

	@$pwlpct=number_format($totalplayoffwins/($totalplayoffwins+$totalplayofflosses),3);
	@$r1wlpct=number_format($first_round_victories/($first_round_victories+$first_round_losses),3);
	@$r2wlpct=number_format($second_round_victories/($second_round_victories+$second_round_losses),3);
	@$r3wlpct=number_format($third_round_victories/($third_round_victories+$third_round_losses),3);
	@$r4wlpct=number_format($fourth_round_victories/($fourth_round_victories+$fourth_round_losses),3);
	$round_victories=$first_round_victories+$second_round_victories+$third_round_victories+$fourth_round_victories;
	$round_losses=$first_round_losses+$second_round_losses+$third_round_losses+$fourth_round_losses;
	@$swlpct=number_format($round_victories/($round_victories+$round_losses),3);
	@$firstpct=number_format($first_wins/($first_wins+$first_losses),3);
	@$secondpct=number_format($second_wins/($second_wins+$second_losses),3);
	@$thirdpct=number_format($third_wins/($third_wins+$third_losses),3);
	@$fourthpct=number_format($fourth_wins/($fourth_wins+$first_losses),3);

	if ($round_one_output != "") {
		$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>First-Round Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P1\" style=\"overflow:auto\">".$round_one_output."</div></td></tr>
			<tr><td><b>Totals:</b> $first_wins - $first_losses ($firstpct)<br>
			<b>Series:</b> $first_round_victories - $first_round_losses ($r1wlpct)</td></tr>";
	}
	if ($round_two_output != "") {
		$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Semis Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P2\" style=\"overflow:auto\">".$round_two_output."</div></td></tr>
			<tr><td><b>Totals:</b> $second_wins - $second_losses ($secondpct)<br>
			<b>Series:</b> $second_round_victories - $second_round_losses ($r2wlpct)</td></tr>";
	}
	if ($round_three_output != "") {
		$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Conference Finals Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P3\" style=\"overflow:auto\">".$round_three_output."</div></td></tr>
			<tr><td><b>Totals:</b> $third_wins - $third_losses ($thirdpct)<br>
			<b>Series:</b> $third_round_victories - $third_round_losses ($r3wlpct)</td></tr>";
	}
	if ($round_four_output != "") {
		$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>IBL Finals Playoff Results</b></font></td></tr>
			<tr><td>
			<div id=\"History-P4\" style=\"overflow:auto\">".$round_four_output."</div></td></tr>
			<tr><td><b>Totals:</b> $fourth_wins - $fourth_losses ($fourthpct)<br>
			<b>Series:</b> $fourth_round_victories - $fourth_round_losses ($r4wlpct)</td></tr>";
	}

	$output=$output."<tr bgcolor=\"#$color1\"><td align=center><font color=\"#$color2\"><b>Post-Season Totals</b></font></td></tr>
		<tr><td><b>Games:</b> $totalplayoffwins - $totalplayofflosses ($pwlpct)</td></tr>
		<tr><td><b>Series:</b> $round_victories - $round_losses ($swlpct)</td></tr>
		</table>";

	$ultimate_output[0]=$output;

	return $ultimate_output;
}

function viewinjuries($tid)
{
	include("header.php");
	OpenTable();

	Shared::displaytopmenu($tid);

	$query="SELECT * FROM nuke_iblplyr WHERE injured > 0 AND retired = 0 ORDER BY ordinal ASC";

	$result=mysql_query($query);
	$num=mysql_numrows($result);

	echo "<center><h2>INJURED PLAYERS</h2></center>
		<table><tr><td valign=top>
		<table class=\"sortable\">
		<tr><th>Pos</th><th>Player</th><th>Team</th><th>Days Injured</th>";

	$query_all_team_colors="SELECT * FROM nuke_ibl_team_info ORDER BY teamid ASC";
	$colors=mysql_query($query_all_team_colors);
	$num_all_team_colors=mysql_num_rows($colors);

	$k=0;
	while ($k < $num_all_team_colors) {
		$color_array[$k]['team_id']=mysql_result($colors,$k,"teamid");
		$color_array[$k]['team_city']=mysql_result($colors,$k,"team_city");
		$color_array[$k]['team_name']=mysql_result($colors,$k,"team_name");
		$color_array[$k]['color1']=mysql_result($colors,$k,"color1");
		$color_array[$k]['color2']=mysql_result($colors,$k,"color2");
		$k++;
	}

	$i=0;

	while ($i < $num) {
		(($i % 2)==0) ? $bgcolor="FFFFFF" : $bgcolor="DDDDDD";

		$name=mysql_result($result,$i,"name");
		$team=mysql_result($result,$i,"teamname");
		$pid=mysql_result($result,$i,"pid");
		$tid=mysql_result($result,$i,"tid");
		$pos=mysql_result($result,$i,"pos");
		$inj=mysql_result($result,$i,"injured");

		$j=0;
		while ($j < $k) {
			$pick_team_name=$color_array[$j]['team_name'];
			if ($pick_team_name == $team) {
				$pick_team_id=$color_array[$j]['team_id'];
				$pick_team_city=$color_array[$j]['team_city'];
				$pick_team_color1=$color_array[$j]['color1'];
				$pick_team_color2=$color_array[$j]['color2'];
			}
			$j++;
		}

		echo "<tr bgcolor=$bgcolor><td>$pos</td><td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td><td bgcolor=\"#$pick_team_color1\"><a href=\"./modules.php?name=Team&op=team&tid=$tid\"><font color=\"#$pick_team_color2\">$pick_team_city $team</font></a></td><td>$inj</td></tr>";

		$i++;
	}

	echo "</table></table>";

	CloseTable();
	include("footer.php");
}

function menu()
{
	$tid = intval($tid);

	include("header.php");
	OpenTable();

	Shared::displaytopmenu($tid);

	CloseTable();
	include("footer.php");
}

function seteditor($user)
{
	global $stop;
	if(!is_user($user)) {
		include("header.php");
		if ($stop) {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
			CloseTable();
		} else {
			OpenTable();
			echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
			CloseTable();
		}
		if (!is_user($user)) {
			OpenTable();
			Shared::displaytopmenu($tid);
			loginbox();
			CloseTable();
		}
		include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		editset($cookie[1]);
	}
}

function editset($username, $bypass=0, $hid=0, $url=0)
{
	global $user, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) ) {
		$board_config[$row['config_name']] = $row['config_value'];
	}

	// ==== PICKUP LOGGED-IN USER INFO

	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);

	// ===== END OF INFO PICKUP

	include("header.php");

	OpenTable();

	// === GRAB TEAM INFORMATION FOR LOGGED-IN USER===

	$teamlogo = $userinfo[user_ibl_team];
	$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
	$tid = mysql_result(mysql_query($queryTeamID), 0);

	Shared::displaytopmenu($tid);

	echo "<hr><center><img src=\"images/logo/$tid.jpg\"><br>";

	$sql3 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName = '$teamlogo' ORDER BY SetNumber ASC ";
	$result3 = $db->sql_query($sql3);
	$num3 = $db->sql_numrows($result3);

	$SetToWorkOn = $_POST['SelectedSet'];

	echo "<table align=center valign=top><tr><th><center>Gameplan Review - $teamlogo</center></th></tr>
		<tr><td><center>
		<form name=\"Set_Editor\" method=\"post\" action=\"./modules.php?name=Team&op=seteditor\"><select name=\"SelectedSet\">";

	$i=0;

	while ($i < 3) {
		$name_of_set=mysql_result($result3,$i,"offense_name");
		$setnumbervalue=mysql_result($result3,$i,"SetNumber");

		if ($SetToWorkOn == $setnumbervalue) {
			echo "	<option value=\"$setnumbervalue\" SELECTED>$name_of_set</option>";
		} else {
			echo "	<option value=\"$setnumbervalue\">$name_of_set</option>";
		}
		$i++;
	}

	echo "</select><input type=\"submit\" value=\"Select This Set\"></form></td></tr>";

	if ($SetToWorkOn == NULL) {} else {
		$sql4 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName = '$teamlogo' AND SetNumber = '$SetToWorkOn'";
		$result4 = $db->sql_query($sql4);
		$num4 = $db->sql_numrows($result4);

		$name1=mysql_result($result4,0,"PG_Depth_Name");
		$name2=mysql_result($result4,0,"SG_Depth_Name");
		$name3=mysql_result($result4,0,"SF_Depth_Name");
		$name4=mysql_result($result4,0,"PF_Depth_Name");
		$name5=mysql_result($result4,0,"C_Depth_Name");

		$low1=mysql_result($result4,0,"PG_Low_Range");;
		$low2=mysql_result($result4,0,"SG_Low_Range");
		$low3=mysql_result($result4,0,"SF_Low_Range");
		$low4=mysql_result($result4,0,"PF_Low_Range");
		$low5=mysql_result($result4,0,"C_Low_Range");

		$high1=mysql_result($result4,0,"PG_High_Range");
		$high2=mysql_result($result4,0,"SG_High_Range");
		$high3=mysql_result($result4,0,"SF_High_Range");
		$high4=mysql_result($result4,0,"PF_High_Range");
		$high5=mysql_result($result4,0,"C_High_Range");

		$totalslots = $high1+$high2+$high3+$high4+$high5-$low1-$low2-$low3-$low4-$low5+5;

		echo "<tr><td><table><tr><th>Pos</th><th>$name1</th><th>$name2</th><th>$name3</th><th>$name4</th><th>$name5</th><th>Roster</th></tr>";

		$j=1;

		while ($j < 10) {

			if ($j == 1) {
				echo "<tr><td>PG</td>";
			} else if ($j == 2) {
				echo "<tr><td>G</td>";
			} else if ($j == 3) {
				echo "<tr><td>SG</td>";
			} else if ($j == 4) {
				echo "<tr><td>GF</td>";
			} else if ($j == 5) {
				echo "<tr><td>SF</td>";
			} else if ($j == 6) {
				echo "<tr><td>F</td>";
			} else if ($j == 7) {
				echo "<tr><td>PF</td>";
			} else if ($j == 8) {
				echo "<tr><td>FC</td>";
			} else {
				echo "<tr><td>C</td>";
			}

			$check1=OffenseSetPositionCheck($j,$low1,$high1);
			if ($check1 == 1 ) {
				echo "<td>OK</td>";
			} else if ($check1 == 2 ) {
				if ($low1+1 == $high1) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=1\">Remove</a></td>";
				}
			} else if ($check1 == 3 ) {
				if ($low1+1 == $high1) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=1\">Remove</a></td>";
				}
			} else if ($check1 == 4 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check1 == 5 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check1 == 6 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else {
				echo "<td>--</td>";
			}

			$check2=OffenseSetPositionCheck($j,$low2,$high2);
			if ($check2 == 1 ) {
				echo "<td>OK</td>";
			} else if ($check2 == 2 ) {
				if ($low2+1 == $high2) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=2\">Remove</a></td>";
				}
			} else if ($check2 == 3 ) {
				if ($low2+1 == $high2) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=2\">Remove</a></td>";
				}
			} else if ($check2 == 4 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=2\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check2 == 5 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=2\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check2 == 6 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else {
				echo "<td>--</td>";
			}

			$check3=OffenseSetPositionCheck($j,$low3,$high3);
			if ($check3 == 1 )
			{
			echo "<td>OK</td>";
			} else if ($check3 == 2 ) {
				if ($low3+1 == $high3) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=3\">Remove</a></td>";
				}
			} else if ($check3 == 3 ) {
				if ($low3+1 == $high3) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=3\">Remove</a></td>";
				}
			} else if ($check3 == 4 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=3\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check3 == 5 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=3\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check3 == 6 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else {
				echo "<td>--</td>";
			}

			$check4=OffenseSetPositionCheck($j,$low4,$high4);
			if ($check4 == 1 ) {
				echo "<td>OK</td>";
			} else if ($check4 == 2 ) {
				if ($low4+1 == $high4) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=4\">Remove</a></td>";
				}
			} else if ($check4 == 3 ) {
				if ($low4+1 == $high4) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=4\">Remove</a></td>";
				}
			} else if ($check4 == 4 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=4\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check4 == 5 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=4\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check4 == 6 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else {
				echo "<td>--</td>";
			}

			$check5=OffenseSetPositionCheck($j,$low5,$high5);
			if ($check5 == 1 )
			{
			echo "<td>OK</td>";
			} else if ($check5 == 2 ) {
				if ($low5+1 == $high5) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=low&position=5\">Remove</a></td>";
				}
			} else if ($check5 == 3 ) {
				if ($low5+1 == $high5) {
					echo "<td>OK</td>";
				} else {
					echo "<td>OK - <a href=\"./modules.php?name=Team&op=changeset&action=remove&set=$SetToWorkOn&type=high&position=5\">Remove</a></td>";
				}
			} else if ($check5 == 4 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=5\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check5 == 5 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=high&position=5\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else if ($check5 == 6 ) {
				if ($totalslots < 22) {
					echo "<td>-- <a href=\"./modules.php?name=Team&op=changeset&action=add&set=$SetToWorkOn&type=low&position=1\">Add</a></td>";
				} else {
					echo "<td>--</td>";
				}
			} else {
				echo "<td>--</td>";
			}

			echo "<td>";

			if ($j == 1) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'PG' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 2) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'G' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 3) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'SG' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 4) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'GF' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 5) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'SF' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 6) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'F' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 7) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'PF' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else if ($j == 8) {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'FC' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			} else {
				$sql5 = "SELECT * FROM nuke_iblplyr WHERE teamname = '$teamlogo' AND altpos = 'C' AND retired = '0' ORDER BY ordinal ASC ";
				$result5 = $db->sql_query($sql5);
				$num5 = $db->sql_numrows($result5);
			}

			$k=0;

			while ($k < $num5) {
				$playername=mysql_result($result5,$k,"name");
				$pid=mysql_result($result5,$k,"pid");

				echo "<a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$playername</a> | ";

				$k++;
			}

			echo "</td></tr>";

			$j++;
		}
		echo "<tr><th colspan=6><center>Total Slots Used (22 max): $totalslots</center></th></tr></table></td></tr>";
	}
	echo "</table>";

	CloseTable();
	include("footer.php");
}

function changeset($user)
{
	global $stop, $action, $set, $type, $position;
	if(!is_user($user)) {
	include("header.php");
	if ($stop) {
		OpenTable();
		echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
		CloseTable();
	} else {
		OpenTable();
		echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
		CloseTable();
	}
	if (!is_user($user)) {
		OpenTable();
		Shared::displaytopmenu($tid);
		loginbox();
		CloseTable();
	}
	include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		changesetgo($cookie[1],$action,$set,$type,$position);
	}
}

function changesetgo($username, $action, $set, $type, $position)
{
	global $user, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) )	{
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	$teamlogo = $userinfo[user_ibl_team];
	if(!$bypass) cookiedecode($user);
	include("header.php");

	OpenTable();
	Shared::displaytopmenu($tid);

	// === CODE TO CHANGE SET ===

	$query_loc=NULL;

	if ($position == 1) {
		$query_loc='PG';
	} else if ($position == 2) {
		$query_loc='SG';
	} else if ($position == 3) {
		$query_loc='SF';
	} else if ($position == 4) {
		$query_loc='PF';
	} else if ($position == 5) {
		$query_loc='C';
	}

	if ($type == 'low') {
		$query_loc=$query_loc.'_Low_Range';
	} else if ($type == 'high') {
		$query_loc=$query_loc.'_High_Range';
	}

	$sqla = "SELECT * FROM nuke_ibl_offense_sets WHERE `TeamName` = '$teamlogo' AND `SetNumber` = '$set'";
	$resulta = $db->sql_query($sqla);

	$newtarget=mysql_result($resulta,0,"$query_loc");

	if ($action == 'add') {
		if ($type == 'low') {
			$newtarget=$newtarget-1;
		} else if ($type == 'high' ) {
			$newtarget=$newtarget+1;
		}
	} else if ($action == 'remove') {
		if ($type == 'low') {
			$newtarget=$newtarget+1;
		} else if ($type == 'high' ) {
			$newtarget=$newtarget-1;
		}
	}

	/***********************************************************************/
	/* ONCE SEASON STARTS DISABLE CHANGE OPTION                            */
	/***********************************************************************/

	$sqlb = "UPDATE nuke_ibl_offense_sets SET `$query_loc` = '$newtarget' WHERE `TeamName` = '$teamlogo' AND `SetNumber` = '$set'";
	$resultb = $db->sql_query($sqlb);

	echo "Your change has been made; please click the button to view changes.";
	//echo "You can't make changes to the editor once the season starts but can only view your game plans.";

	echo "<form name=\"Set_Editor\" method=\"post\" action=\"./modules.php?name=Team&op=seteditor\">
		<input type=\"hidden\" name=\"SelectedSet\" value=\"$set\"><input type=\"submit\" value=\"Return to Set Editor\"></form>";
	CloseTable();
}

	/************************************************************************/
	/* END CHANGE OFFENSE SET                                               */
	/************************************************************************/

function OffenseSetPositionCheck($Slot, $Low, $High)
{
	//echo "Slot:$Slot<br>Low:$Low<br>High:$High<br><br>";
	if ($Low > $High) {
		return 6;
	}

	if ($Low < $Slot) {
		if ($High > $Slot) {
			return 1;
		}
	}

	if ($Low == $Slot) {
		return 2;
	}

	if ($High == $Slot) {
		return 3;
	}

	if ($Low == ($Slot+1)) {
		return 4;
	}

	if ($High == ($Slot-1)) {
		return 5;
	}

	return 0;
}

/*
function viewtraining($user) {
	global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
	if(!is_user($user)) {
	include("header.php");
	if ($stop) {
		OpenTable();
			Shared::displaytopmenu($tid);

		echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
		CloseTable();
		echo "<br>\n";
	} else {
		OpenTable();
			Shared::displaytopmenu($tid);
		echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
		CloseTable();
		echo "<br>\n";
	}
	if (!is_user($user)) {
		OpenTable();
			Shared::displaytopmenu($tid);

		mt_srand ((double)microtime()*1000000);
		$maxran = 1000000;
		$random_num = mt_rand(0, $maxran);
		echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
		if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
			."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
			."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
		}
		echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
		CloseTable();
	}
	include("footer.php");
	} elseif (is_user($user)) {
		global $cookie;
		cookiedecode($user);
		trainingpage($cookie[1]);
	}

}

function trainingpage($username)

{
	global $user, $cookie, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) )
	{
	$board_config[$row['config_name']] = $row['config_value'];
	}

// ==== PICKUP LOGGED-IN USER INFO

	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);

// ===== END OF INFO PICKUP

	include("header.php");

	OpenTable();
	Shared::displaytopmenu($tid);

// === GRAB TEAM INFORMATION FOR LOGGED-IN USER===


	$teamlogo = $userinfo[user_ibl_team];
	$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
	$tid = mysql_result(mysql_query($queryTeamID), 0);

	echo "<hr>
	<center><img src=\"images/logo/$tid.jpg\"><br>
";

$Team_Name = $_POST['Team_Name'];
$k = 0;
if ($Team_Name != NULL )
{
	while ( $k < 5)
	{
		if ($k == 0)
		{
			$n_fga = $_POST['fga0'];
			$n_fgp = $_POST['fgp0'];
			$n_fta = $_POST['fta0'];
			$n_ftp = $_POST['ftp0'];
			$n_tga = $_POST['tga0'];
			$n_tgp = $_POST['tgp0'];
			$n_orb = $_POST['orb0'];
			$n_drb = $_POST['drb0'];
			$n_ast = $_POST['ast0'];
			$n_stl = $_POST['stl0'];
			$n_tvr = $_POST['tvr0'];
			$n_blk = $_POST['blk0'];
			$n_oo = $_POST['oo0'];
			$n_do = $_POST['do0'];
			$n_po = $_POST['po0'];
			$n_to = $_POST['to0'];
			$n_od = $_POST['od0'];
			$n_dd = $_POST['dd0'];
			$n_pd = $_POST['pd0'];
			$n_td = $_POST['td0'];
			$n_Foul = $_POST['Foul0'];
			$n_Sta = $_POST['Sta0'];
			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 1) {
			$n_fga = $_POST['fga1'];
			$n_fgp = $_POST['fgp1'];
			$n_fta = $_POST['fta1'];
			$n_ftp = $_POST['ftp1'];
			$n_tga = $_POST['tga1'];
			$n_tgp = $_POST['tgp1'];
			$n_orb = $_POST['orb1'];
			$n_drb = $_POST['drb1'];
			$n_ast = $_POST['ast1'];
			$n_stl = $_POST['stl1'];
			$n_tvr = $_POST['tvr1'];
			$n_blk = $_POST['blk1'];
			$n_oo = $_POST['oo1'];
			$n_do = $_POST['do1'];
			$n_po = $_POST['po1'];
			$n_to = $_POST['to1'];
			$n_od = $_POST['od1'];
			$n_dd = $_POST['dd1'];
			$n_pd = $_POST['pd1'];
			$n_td = $_POST['td1'];
			$n_Foul = $_POST['Foul1'];
			$n_Sta = $_POST['Sta1'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 2) {
			$n_fga = $_POST['fga2'];
			$n_fgp = $_POST['fgp2'];
			$n_fta = $_POST['fta2'];
			$n_ftp = $_POST['ftp2'];
			$n_tga = $_POST['tga2'];
			$n_tgp = $_POST['tgp2'];
			$n_orb = $_POST['orb2'];
			$n_drb = $_POST['drb2'];
			$n_ast = $_POST['ast2'];
			$n_stl = $_POST['stl2'];
			$n_tvr = $_POST['tvr2'];
			$n_blk = $_POST['blk2'];
			$n_oo = $_POST['oo2'];
			$n_do = $_POST['do2'];
			$n_po = $_POST['po2'];
			$n_to = $_POST['to2'];
			$n_od = $_POST['od2'];
			$n_dd = $_POST['dd2'];
			$n_pd = $_POST['pd2'];
			$n_td = $_POST['td2'];
			$n_Foul = $_POST['Foul2'];
			$n_Sta = $_POST['Sta2'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 3) {
			$n_fga = $_POST['fga3'];
			$n_fgp = $_POST['fgp3'];
			$n_fta = $_POST['fta3'];
			$n_ftp = $_POST['ftp3'];
			$n_tga = $_POST['tga3'];
			$n_tgp = $_POST['tgp3'];
			$n_orb = $_POST['orb3'];
			$n_drb = $_POST['drb3'];
			$n_ast = $_POST['ast3'];
			$n_stl = $_POST['stl3'];
			$n_tvr = $_POST['tvr3'];
			$n_blk = $_POST['blk3'];
			$n_oo = $_POST['oo3'];
			$n_do = $_POST['do3'];
			$n_po = $_POST['po3'];
			$n_to = $_POST['to3'];
			$n_od = $_POST['od3'];
			$n_dd = $_POST['dd3'];
			$n_pd = $_POST['pd3'];
			$n_td = $_POST['td3'];
			$n_Foul = $_POST['Foul3'];
			$n_Sta = $_POST['Sta3'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		} elseif ($k == 4) {
			$n_fga = $_POST['fga4'];
			$n_fgp = $_POST['fgp4'];
			$n_fta = $_POST['fta4'];
			$n_ftp = $_POST['ftp4'];
			$n_tga = $_POST['tga4'];
			$n_tgp = $_POST['tgp4'];
			$n_orb = $_POST['orb4'];
			$n_drb = $_POST['drb4'];
			$n_ast = $_POST['ast4'];
			$n_stl = $_POST['stl4'];
			$n_tvr = $_POST['tvr4'];
			$n_blk = $_POST['blk4'];
			$n_oo = $_POST['oo4'];
			$n_do = $_POST['do4'];
			$n_po = $_POST['po4'];
			$n_to = $_POST['to4'];
			$n_od = $_POST['od4'];
			$n_dd = $_POST['dd4'];
			$n_pd = $_POST['pd4'];
			$n_td = $_POST['td4'];
			$n_Foul = $_POST['Foul4'];
			$n_Sta = $_POST['Sta4'];

			$n_total=$n_fga+$n_fgp+$n_fta+$n_ftp+$n_tga+$n_tgp+$n_orb+$n_drb+$n_ast+$n_stl+$n_tvr+$n_blk+$n_oo+$n_do+$n_po+$n_to+$n_od+$n_dd+$n_pd+$n_td+$n_Foul+$n_Sta;

		}
		$k++;

		if ($n_fga < 1 || $n_fgp < 1 || $n_fta < 1 || $n_ftp < 1 || $n_tga < 1 || $n_tgp < 1 || $n_orb < 1 || $n_drb < 1 || $n_ast < 1 || $n_stl < 1 || $n_tvr < 1 || $n_blk < 1 || $n_oo < 1 || $n_do < 1 || $n_po < 1 || $n_to < 1 || $n_od < 1 || $n_dd < 1 || $n_pd < 1 || $n_td < 1 || $n_Foul < 1 || $n_Sta < 1)
		{
			echo "<b>Error!  Each category has to have more than 0 points allocated!</b>";
		} elseif ($n_fga > 7 || $n_fgp > 7 || $n_fta > 7 || $n_ftp > 7 || $n_tga > 7 || $n_tgp > 7 || $n_orb > 7 || $n_drb > 7 || $n_ast > 7 || $n_stl > 7 || $n_tvr > 7 || $n_blk > 7 || $n_oo > 7 || $n_do > 7 || $n_po > 7 || $n_to > 7 || $n_od > 7 || $n_dd > 7 || $n_pd > 7 || $n_td > 7 || $n_Foul > 7 || $n_Sta > 7) {
				echo "<b>Cheater!  Each category can't have more than 7 points allocated!</b>";
		} else {

			if ($n_total < 89)
			{
			$sqla = "UPDATE nuke_ibl_training SET `2ga` = '$n_fga', `2gp` = '$n_fgp', `fta` = '$n_fta', `ftp` = '$n_ftp', `3ga` = '$n_tga', `3gp` = '$n_tgp', `orb` = '$n_orb', `drb` = '$n_drb', `ast` = '$n_ast', `stl` = '$n_stl', `tvr` = '$n_tvr', `blk` = '$n_blk', `oo` = '$n_oo', `do` = '$n_do', `po` = '$n_po', `to` = '$n_to', `od` = '$n_od', `dd` = '$n_dd', `pd` = '$n_pd', `td` = '$n_td', `Foul` = '$n_Foul', `Sta` = '$n_Sta' WHERE `teamname` = '$teamlogo' AND `training_set` = '$k'";
			$resultadj= $db->sql_query($sqla);
			}
			if ($n_total > 88)
			{
			echo "Error!  Too many training points allocated (you allocated $n_total, you can only allocate 88). Changes not committed!";
			}
		}
	}
}

	$sqltr = "SELECT * FROM nuke_ibl_training WHERE teamname = '$teamlogo' ";
	$resulttr = $db->sql_query($sqltr);
	$numtr = $db->sql_numrows($resulttr);
	$i = 0;

	echo "<form name = \"Training\" method=\"post\" action=\"./modules.php?name=Team&op=training\">
	<input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\">
	<table border=1><tr>";

	while ($i < $numtr)
	{
		$training_pref_set=mysql_result($resulttr,$i,"training_set");
		$fga=mysql_result($resulttr,$i,"2ga");
		$fgp=mysql_result($resulttr,$i,"2gp");
		$fta=mysql_result($resulttr,$i,"fta");
		$ftp=mysql_result($resulttr,$i,"ftp");
		$tga=mysql_result($resulttr,$i,"3ga");
		$tgp=mysql_result($resulttr,$i,"3gp");
		$orb=mysql_result($resulttr,$i,"orb");
		$drb=mysql_result($resulttr,$i,"drb");
		$ast=mysql_result($resulttr,$i,"ast");
		$stl=mysql_result($resulttr,$i,"stl");
		$tvr=mysql_result($resulttr,$i,"tvr");
		$blk=mysql_result($resulttr,$i,"blk");

		$oo=mysql_result($resulttr,$i,"oo");
		$do=mysql_result($resulttr,$i,"do");
		$po=mysql_result($resulttr,$i,"po");
		$to=mysql_result($resulttr,$i,"to");
		$od=mysql_result($resulttr,$i,"od");
		$dd=mysql_result($resulttr,$i,"dd");
		$pd=mysql_result($resulttr,$i,"pd");
		$td=mysql_result($resulttr,$i,"td");
		$Foul=mysql_result($resulttr,$i,"Foul");
		$Sta=mysql_result($resulttr,$i,"Sta");



	if ($training_pref_set == 1)
		{
			$traintotal11=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal11 of 88 maximum<br><br>PG</center></th></tr>";

		} elseif ($training_pref_set == 2) {
			$traintotal12=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal12 of 88 maximum<br><br>SG</center></th></tr>";
		} elseif ($training_pref_set == 3) {
			$traintotal13=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal13 of 88 maximum<br><br>SF</center></th></tr>";
		} elseif ($training_pref_set == 4) {
			$traintotal14=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal14 of 88 maximum<br><br>PF</center></th></tr>";
		} elseif ($training_pref_set == 5) {
			$traintotal15=$fga+$fgp+$fta+$ftp+$tga+$tgp+$orb+$drb+$ast+$stl+$tvr+$blk+$oo+$do+$po+$to+$od+$dd+$pd+$td+$Foul+$Sta;
			echo "<td><table border=1><tr><th colspan=2><center>Total Training Points Used: $traintotal15 of 88 maximum<br><br>C</center></th></tr>";
		}
	echo "<tr><td><center>Create Two-Point Shots</center></td><td><center><input type=\"text\" name=\"fga$i\" size=\"1\" value=\"$fga\"></center></td></tr>
	<tr><td><center>Two-Point Accuracy</center></td><td><center><input type=\"text\" name=\"fgp$i\" size=\"1\" value=\"$fgp\"></center></td></tr>
	<tr><td><center>Draw Fouls</center></td><td><center><input type=\"text\" name=\"fta$i\" size=\"1\" value=\"$fta\"></center></td></tr>
	<tr><td><center>Free Throw Accuracy</center></td><td><center><input type=\"text\" name=\"ftp$i\" size=\"1\" value=\"$ftp\"></center></td></tr>
	<tr><td><center>Create Three-point Shots</center></td><td><center><input type=\"text\" name=\"tga$i\" size=\"1\" value=\"$tga\"></center></td></tr>
	<tr><td><center>Three-Point accuracy</center></td><td><center><input type=\"text\" name=\"tgp$i\" size=\"1\" value=\"$tgp\"></center></td></tr>
	<tr><td><center>Offensive Rebounding</center></td><td><center><input type=\"text\" name=\"orb$i\" size=\"1\" value=\"$orb\"></center></td></tr>
	<tr><td><center>Defensive Rebounding</center></td><td><center><input type=\"text\" name=\"drb$i\" size=\"1\" value=\"$drb\"></center></td></tr>
	<tr><td><center>Passing/Assists</center></td><td><center><input type=\"text\" name=\"ast$i\" size=\"1\" value=\"$ast\"></center></td></tr>
	<tr><td><center>Steals</center></td><td><center><input type=\"text\" name=\"stl$i\" size=\"1\" value=\"$stl\"></center></td></tr>
	<tr><td><center>Avoid Turnovers</center></td><td><center><input type=\"text\" name=\"tvr$i\" size=\"1\" value=\"$tvr\"></center></td></tr>
	<tr><td><center>Block Shots</center></td><td><center><input type=\"text\" name=\"blk$i\" size=\"1\" value=\"$blk\"></center></td></tr>
	<tr><td><center>Outside Offense</center></td><td><center><input type=\"text\" name=\"oo$i\" size=\"1\" value=\"$oo\"></center></td></tr>
	<tr><td><center>Drive Offense</center></td><td><center><input type=\"text\" name=\"do$i\" size=\"1\" value=\"$do\"></center></td></tr>
	<tr><td><center>Post Offense</center></td><td><center><input type=\"text\" name=\"po$i\" size=\"1\" value=\"$po\"></center></td></tr>
	<tr><td><center>Transition Offense</center></td><td><center><input type=\"text\" name=\"to$i\" size=\"1\" value=\"$to\"></center></td></tr>
	<tr><td><center>Outside Defense</center></td><td><center><input type=\"text\" name=\"od$i\" size=\"1\" value=\"$od\"></center></td></tr>
	<tr><td><center>Drive Defense</center></td><td><center><input type=\"text\" name=\"dd$i\" size=\"1\" value=\"$dd\"></center></td></tr>
	<tr><td><center>Post Defense</center></td><td><center><input type=\"text\" name=\"pd$i\" size=\"1\" value=\"$pd\"></center></td></tr>
	<tr><td><center>Transition Defense</center></td><td><center><input type=\"text\" name=\"td$i\" size=\"1\" value=\"$td\"></center></td></tr>
	<tr><td><center>Avoid Fouls</center></td><td><center><input type=\"text\" name=\"Foul$i\" size=\"1\" value=\"$Foul\"></center></td></tr>
	<tr><td><center>Stamina</center></td><td><center><input type=\"text\" name=\"Sta$i\" size=\"1\" value=\"$Sta\"></center></td></tr></table></td>";
	$i++;
	}
echo"</tr>
<tr><td colspan=5><center><input type=\"submit\" value=\"Process Changes\"></center></form></td></tr>
</table>
As a reminder, you should enter between 1 and 7 for each training value.  It is suggested that in order to make sure your training is legal, that you first lower one value before raising another; if you try to submit training with more than 88 total points, it will be illegal and none of your changes will be saved (though depending on your browser cache, the 'BACK' button may help you out!
";

	CloseTable();
	include("footer.php");

}
*/

function financialdisplay($matrix,$yr,$tid)
{
	$financial_result=$matrix;
	$contract_matrix=$financial_result[0];

	$slot_year_1=$financial_result[1];
	$slot_year_2=$financial_result[2];
	$slot_year_3=$financial_result[3];
	$slot_year_4=$financial_result[4];
	$slot_year_5=$financial_result[5];
	$slot_year_6=$financial_result[6];
	$season_min=$financial_result[7];
	$season_max=$financial_result[8];

	if ($yr == NULL) {
		$yr=$season_min;
	}

	// NEW CBA FINANCIAL PLANNER
	$iteration=1;
	$cap_amount[1]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_1' "),0,"value");
	$cap_amount[2]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_2' "),0,"value");
	$cap_amount[3]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_3' "),0,"value");
	$cap_amount[4]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_4' "),0,"value");
	$cap_amount[5]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_5' "),0,"value");
	$cap_amount[6]=mysql_result(mysql_query("SELECT value FROM nuke_ibl_settings WHERE name = 'Hard_Cap_Year_6' "),0,"value");

	while ($season_min < $season_max) {
		$cap_space=$cap_amount[$iteration];
		$output[$season_min]="<tr><td>
		<table  align=\"center\" class=\"sortable\"><thead>
		<tr bgcolor=$color1><th><font color=$color2>Slot</font></th><th><font color=$color2>Item</font></th><td><font color=$color2>Type</font></th><th><font color=$color2>Cap Amount</font></th>
		</thead><tbody>";

		$cap_i=0;
		$max_i=15;
		if ($iteration == 1) {
			$slot_year_top=$slot_year_1;
		} else if ($iteration == 2) {
			$slot_year_top=$slot_year_2;
		} else if ($iteration == 3) {
			$slot_year_top=$slot_year_3;
		} else if ($iteration == 4) {
			$slot_year_top=$slot_year_4;
		} else if ($iteration == 5) {
			$slot_year_top=$slot_year_5;
		} else if ($iteration == 6) {
			$slot_year_top=$slot_year_6;
		}
		if ($slot_year_top > $max_i) {
			$max_i=$slot_year_top;
		}
		while ($cap_i < $max_i)	{
			$output_pid=$contract_matrix[$season_min][$cap_i]["pid"];
			$output_name=$contract_matrix[$season_min][$cap_i]["name"];
			$output_type=$contract_matrix[$season_min][$cap_i]["type"];
			$output_amount=$contract_matrix[$season_min][$cap_i]["amount"];
			$cap_i++;
			if ($output_type == "contract" OR $output_type == "waiver contract" ) {
				$output[$season_min]=$output[$season_min]."<tr><td>$cap_i</td><td><a href=\"modules.php?name=Player&pa=showpage&pid=$output_pid\">$output_name</a></td><td>$output_type</td><td>$output_amount</td></tr>";
			} else {
				$output[$season_min]=$output[$season_min]."<tr><td>$cap_i</td><td>$output_name</td><td>$output_type</td><td>$output_amount</td></tr>";
			}
			$cap_space=$cap_space-$output_amount;
		}
		$output[$season_min]."</tbody>
		<tfoot><tr><td colspan=2><b>TOTAL REMAINING CAP ROOM, $season_min</b></td><td>$cap_space</td></tr>
		</tfoot></table>";

		if ($yr == $season_min) {
			$bottom_output=$bottom_output."<td bgcolor=#777777><a href=\"modules.php?name=Team&op=finances&tid=$tid&yr=$season_min\">$season_min</a> ($cap_space)</td>";
		} else {
			$bottom_output=$bottom_output."<td><a href=\"modules.php?name=Team&op=finances&tid=$tid&yr=$season_min\">$season_min</a> ($cap_space)</td>";
		}

		$iteration++;
		$season_min++;
	}

	$returning[0]=$output;
	$returning[1]=$bottom_output;

	return $returning;
}

function standings ($team)
{
	$query="SELECT * FROM nuke_ibl_power WHERE Team = '$team'";
	$result=mysql_query($query);
	$num=mysql_numrows($result);
	$Team=mysql_result($result,0,"Team");
	$win=mysql_result($result,$i,"win");
	$loss=mysql_result($result,$i,"loss");
	$gb=mysql_result($result,0,"gb");
	$division=mysql_result($result,0,"Division");
	$conference=mysql_result($result,0,"Conference");
	$home_win=mysql_result($result,0,"home_win");
	$home_loss=mysql_result($result,0,"home_loss");
	$road_win=mysql_result($result,0,"road_win");
	$road_loss=mysql_result($result,0,"road_loss");
	$last_win=mysql_result($result,0,"last_win");
	$last_loss=mysql_result($result,0,"last_loss");

	$query2="SELECT * FROM nuke_ibl_power WHERE Division = '$division' ORDER BY gb DESC";
	$result2=mysql_query($query2);
	$num=mysql_numrows($result2);
	$i=0;
	$gbbase=mysql_result($result2,$i,"gb");
	$gb=$gbbase-$gb;
	while ($i < $num) {
		$Team2=mysql_result($result2,$i,"Team");
		if ($Team2 == $Team) {
			$Div_Pos=$i+1;
		}
		$i++;
	}

	$query3="SELECT * FROM nuke_ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
	$result3=mysql_query($query3);
	$num=mysql_numrows($result3);
	$i=0;
	while ($i < $num) {
		$Team3=mysql_result($result3,$i,"Team");
		if ($Team3 == $Team) {
			$Conf_Pos=$i+1;
		}
		$i++;
	}

	$standings=$standings."<table><tr><td align='right'><b>Team:</td><td>$team</td></tr>
		<tr><td align='right'><b>Record:</td><td>$win-$loss</td></tr>
		<tr><td align='right'><b>Conference:</td><td>$conference</td></tr>
		<tr><td align='right'><b>Conf Position:</td><td>$Conf_Pos</td></tr>
		<tr><td align='right'><b>Division:</td><td>$division</td></tr>
		<tr><td align='right'><b>Div Position:</td><td>$Div_Pos</td></tr>
		<tr><td align='right'><b>GB:</td><td>$gb</td></tr>
		<tr><td align='right'><b>Home Record:</td><td>$home_win-$home_loss</td></tr>
		<tr><td align='right'><b>Road Record:</td><td>$road_win-$road_loss</td></tr>
		<tr><td align='right'><b>Last 10:</td><td>$last_win-$last_loss</td></tr>
		</table>";
	return $standings;
}


function asg_voting()
{
	global $user, $cookie, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) )	{
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);
	include("header.php");
	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	$teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));
	$sql3 = "SELECT * FROM " . $prefix . "_ibl_settings WHERE name = 'ASG Voting'";
	$result3 = $db->sql_query($sql3);
	$num3 = $db->sql_numrows($result3);
	$info = $db->sql_fetchrow($result3);
	$voting_active = stripslashes(check_html($info['value'], "nohtml"));

	OpenTable();

	if ($voting_active == "No") {
		echo "Sorry, ASG voting is not yet active.<br>";
	} else {
		if ($teamlogo == "") {
			echo "Sorry, you must be logged in to vote.<br>";
		} else {
			$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
			$tid = mysql_result(mysql_query($queryTeamID), 0);
			echo "<form name=\"ASGVote\" method=\"post\" action=\"./ASGVote.php\"><center><img src=\"images/logo/$tid.jpg\"><br><br>";
			$query = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' and stats_gm > '10' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result = mysql_query($query);
			while($row = mysql_fetch_assoc($result)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$dd .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query1 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result1 = mysql_query($query1);
			while($row = mysql_fetch_assoc($result1)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$ff .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query2 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result2 = mysql_query($query2);
			while($row = mysql_fetch_assoc($result2)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$hh .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query3 = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result3 = mysql_query($query3);
			while($row = mysql_fetch_assoc($result3)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$ii .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query4 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result4 = mysql_query($query4);
			while($row = mysql_fetch_assoc($result4)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$jj .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query5 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result5 = mysql_query($query5);
			while($row = mysql_fetch_assoc($result5)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$kk .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			echo "
				<select name=\"ECC\">
					<option value=\"\">Select Your Eastern Conference Center...</option>
					<option value=\"$dd\">$dd</option>
				</select><br><br>
				<select name=\"ECF1\">
					<option value=\"\">Select Your First Eastern Conference Forward...</option>
					<option value=\"$ff\">$ff</option>
				</select><br><br>
				<select name=\"ECF2\">
					<option value=\"\">Select Your Second Eastern Conference Forward...</option>
					<option value=\"$ff\">$ff</option>
				</select><br><br>
				<select name=\"ECG1\">
					<option value=\"\">Select Your First Eastern Conference Guard...</option>
					<option value=\"$hh\">$hh</option>
				</select><br><br>
				<select name=\"ECG2\">
					<option value=\"\">Select Your Second Eastern Conference Guard...</option>
					<option value=\"$hh\">$hh</option>
				</select><br><br>
				<select name=\"WCC\">
					<option value=\"\">Select Your Western Conference Center...</option>
					<option value=\"$ii\">$ii</option>
				</select><br><br>
				<select name=\"WCF1\">
					<option value=\"\">Select Your First Western Conference Forward...</option>
					<option value=\"$jj\">$jj</option>
				</select><br><br>
				<select name=\"WCF2\">
					<option value=\"\">Select Your Second Western Conference Forward...</option>
					<option value=\"$jj\">$jj</option>
				</select><br><br>
				<select name=\"WCG1\">
					<option value=\"\">Select Your First Western Conference Guard...</option>
					<option value=\"$kk\">$kk</option>
				</select><br><br>
				<select name=\"WCG2\">
					<option value=\"\">Select Your Second Western Conference Guard...</option>
					<option value=\"$kk\">$kk</option>
				</select>
				</td></tr>
				<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">
				<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
				<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
				</table>
				<center><input type=\"submit\" value=\"Submit Votes!\"></center>
				</form>
			";
			CloseTable();
			include("footer.php");
		}
	}
}

function eoy_voting()
{
	global $user, $cookie, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) ) {
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);
	include("header.php");
	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	$teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));

	$sql3 = "SELECT * FROM " . $prefix . "_ibl_settings WHERE name = 'EOY Voting'";
	$result3 = $db->sql_query($sql3);
	$num3 = $db->sql_numrows($result3);
	$info = $db->sql_fetchrow($result3);
	$voting_active = stripslashes(check_html($info['value'], "nohtml"));

	OpenTable();

	if ($voting_active == "No") {
		echo "Sorry, EOY voting is not yet active.<br>";
	} else {
		if ($teamlogo == "") {
			echo "Sorry, you must be logged in to vote.<br>";
		} else {
			$queryTeamID = "SELECT teamid FROM nuke_ibl_team_info WHERE team_name = '$teamlogo'";
			$tid = mysql_result(mysql_query($queryTeamID), 0);
			echo "<form name=\"EOYVote\" method=\"post\" action=\"./EOYVote.php\"><center><img src=\"images/logo/$tid.jpg\"><br><br>";
			$query = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and stats_gm >= '55' and (stats_min/stats_gm) >= 24 order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";

			$result = mysql_query($query);
			while($row = mysql_fetch_assoc($result)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$dd .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query1 = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and (stats_gs/stats_gm) <= '0.5' and stats_gm > '55' order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result1 = mysql_query($query1);
			while($row = mysql_fetch_assoc($result1)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$ff .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query2 = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and exp = '1' and stats_gm >= '55' and (stats_min/stats_gm) >= 24 order by (((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)+stats_orb+stats_drb+(2*stats_ast)+(2*stats_stl)+(2*stats_blk)-((stats_fga-stats_fgm)+(stats_fta-stats_ftm)+stats_to+stats_pf))/stats_gm desc";
			$result2 = mysql_query($query2);
			while($row = mysql_fetch_assoc($result2)) {
				$ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
				$ppg = round($ppg,1);
				$rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
				$rpg = round($rpg,1);
				$apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
				$apg = round($apg,1);
				$spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
				$spg = round($spg,1);
				$tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
				$tpg = round($tpg,1);
				$bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
				$bpg = round($bpg,1);
				$fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
				$fgp = round($fgp,3);
				$ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
				$ftp = round($ftp,3);
				$tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
				$tpp = round($tpp,3);
				$gm = floatval($row['stats_gm']);
				$gs = floatval($row['stats_gs']);
				$hh .= "<option value='".$row['name'].", ".$row['teamname']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
			}
			$query3 = "SELECT * from nuke_ibl_team_info where teamid != '35' order by owner_name";
			$result3 = mysql_query($query3);
			while($row = mysql_fetch_assoc($result3)) {
				$ii .= "<option value='".$row['owner_name'].", ".$row['team_city']." ".$row['team_name']."'>".$row['owner_name'].", ".$row['team_city']." ".$row['team_name']."</option>";
			}
			echo "
				<select name=\"MVP1\">
					<option value=\"\">Select Your First Choice for Most Valuable Player...</option>
					<option value=\"$dd\">$dd</option>
				</select><br><br>
				<select name=\"MVP2\">
					<option value=\"\">Select Your Second Choice for Most Valuable Player...</option>
					<option value=\"$dd\">$dd</option>
				</select><br><br>
				<select name=\"MVP3\">
					<option value=\"\">Select Your Third Choice for Most Valuable Player...</option>
					<option value=\"$dd\">$dd</option>
				</select><br><br>
				<select name=\"Six1\">
					<option value=\"\">Select Your First Choice for Sixth Man of the Year...</option>
					<option value=\"$ff\">$ff</option>
				</select><br><br>
				<select name=\"Six2\">
					<option value=\"\">Select Your Second Choice for Sixth Man of the Year...</option>
					<option value=\"$ff\">$ff</option>
				</select><br><br>
				<select name=\"Six3\">
					<option value=\"\">Select Your Third Choice for Sixth Man of the Year...</option>
					<option value=\"$ff\">$ff</option>
				</select><br><br>
				<select name=\"ROY1\">
					<option value=\"\">Select Your First Choice for Rookie of the Year...</option>
					<option value=\"$hh\">$hh</option>
				</select><br><br>
				<select name=\"ROY2\">
					<option value=\"\">Select Your Second Choice for Rookie of the Year...</option>
					<option value=\"$hh\">$hh</option>
				</select><br><br>
				<select name=\"ROY3\">
					<option value=\"\">Select Your Third Choice for Rookie of the Year...</option>
					<option value=\"$hh\">$hh</option>
				</select><br><br>
				<select name=\"GM1\">
					<option value=\"\">Select Your First Choice for GM of the Year...</option>
					<option value=\"$ii\">$ii</option>
				</select><br><br>
				<select name=\"GM2\">
					<option value=\"\">Select Your Second Choice for GM of the Year...</option>
					<option value=\"$ii\">$ii</option>
				</select><br><br>
				<select name=\"GM3\">
					<option value=\"\">Select Your First Choice for GM of the Year...</option>
					<option value=\"$ii\">$ii</option>
				</select>
				</td></tr>
				<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">
				<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
				<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
				</table>
				<center><input type=\"submit\" value=\"Submit Votes!\"></center>
				</form>
			";

			CloseTable();
			include("footer.php");
		}
	}
}

function asg_results()
{
	global $user, $cookie, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) )	{
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);
	include("header.php");
	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	$teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));
	$user = stripslashes(check_html($userinfo['username'], "nohtml"));
	OpenTable();

	if (($user !== 'chibul') && ($user != 'Joe') && ($user != 'eggman')) {
		echo "Sorry, only the IBL Executive Staff may view this page.<br>";
	} else {
		$query1="select count(name) as votes,name from (select East_C as name from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result1=mysql_query($query1);
		$num1=mysql_num_rows($result1);
		$query2="select count(name) as votes,name from (select East_F1 as name from IBL_ASG_Votes union all select East_F2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result2=mysql_query($query2);
		$num2=mysql_num_rows($result2);
		$query3="select count(name) as votes,name from (select East_G1 as name from IBL_ASG_Votes union all select East_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result3=mysql_query($query3);
		$num3=mysql_num_rows($result3);
		$query4="select count(name) as votes,name from (select West_C as name from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result4=mysql_query($query4);
		$num4=mysql_num_rows($result4);
		$query5="select count(name) as votes,name from (select West_F1 as name from IBL_ASG_Votes union all select West_F2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result5=mysql_query($query5);
		$num5=mysql_num_rows($result5);
		$query6="select count(name) as votes,name from (select West_G1 as name from IBL_ASG_Votes union all select West_G2 from IBL_ASG_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result6=mysql_query($query6);
		$num6=mysql_num_rows($result6);

		//    OpenTable();

		$k=0;
		$h=0;
		$i=0;
		$m=0;
		$n=0;
		$o=0;
		while ($k < $num1) {
			$player[$k]=mysql_result($result1,$k, "name");
			$votes[$k]=mysql_result($result1,$k);
			$table_echo=$table_echo."<tr><td>".$player[$k]."</td><td>".$votes[$k]."</td></tr>";
			$k++;
		}

		$text=$text."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo</table><br><br>";
		while ($h < $num2) {
			$player[$h]=mysql_result($result2,$h, "name");
			$votes[$h]=mysql_result($result2,$h);
			$table_echo1=$table_echo1."<tr><td>".$player[$h]."</td><td>".$votes[$h]."</td></tr>";
			$h++;
		}
		$text1=$text1."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo1</table><br><br>";
		while ($i < $num3) {
			$player[$i]=mysql_result($result3,$i, "name");
			$votes[$i]=mysql_result($result3,$i);
			$table_echo2=$table_echo2."<tr><td>".$player[$i]."</td><td>".$votes[$i]."</td></tr>";
			$i++;
		}
		$text2=$text2."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo2</table><br><br>";
		while ($m < $num4) {
			$player[$m]=mysql_result($result4,$m, "name");
			$votes[$m]=mysql_result($result4,$m);
			$table_echo3=$table_echo3."<tr><td>".$player[$m]."</td><td>".$votes[$m]."</td></tr>";
			$m++;
		}
		$text3=$text3."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo3</table><br><br>";
		while ($n < $num5) {
			$player[$n]=mysql_result($result5,$n, "name");
			$votes[$n]=mysql_result($result5,$n);
			$table_echo4=$table_echo4."<tr><td>".$player[$n]."</td><td>".$votes[$n]."</td></tr>";
			$n++;
		}
		$text4=$text4."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo4</table><br><br>";
		while ($o < $num6) {
			$player[$o]=mysql_result($result6,$o, "name");
			$votes[$o]=mysql_result($result6,$o);
			$table_echo5=$table_echo5."<tr><td>".$player[$o]."</td><td>".$votes[$o]."</td></tr>";
			$o++;
		}
		$text5=$text5."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo5</table><br><br>";

		echo "<b>Eastern Conference Center</b>";
		echo $text;
		echo "<b>Eastern Conference Forward</b>";
		echo $text1;
		echo "<b>Eastern Conference Guard</b>";
		echo $text2;
		echo "<b>Western Conference Center</b>";
		echo $text3;
		echo "<b>Western Conference Forward</b>";
		echo $text4;
		echo "<b>Western Conference Guard</b>";
		echo $text5;
		CloseTable();
		include("footer.php");
	}
}

function eoy_results()
{
	global $user, $cookie, $prefix, $user_prefix, $db;
	$sql = "SELECT * FROM ".$prefix."_bbconfig";
	$result = $db->sql_query($sql);
	while ( $row = $db->sql_fetchrow($result) ) {
		$board_config[$row['config_name']] = $row['config_value'];
	}
	$sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
	$result2 = $db->sql_query($sql2);
	$num = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	if(!$bypass) cookiedecode($user);
	include("header.php");
	$sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
	$result2 = $db->sql_query($sql2);
	$num2 = $db->sql_numrows($result2);
	$userinfo = $db->sql_fetchrow($result2);
	$teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));
	$user = stripslashes(check_html($userinfo['username'], "nohtml"));
	OpenTable();

	if (($user !== 'chibul') && ($user != 'Joe') && ($user != 'eggman'))  {
		echo "Sorry, only the IBL Executive Staff may view this page.<br>";
	} else {
		$query1="select sum(score) as votes,name from (select MVP_1 as name, 3 as score from IBL_EOY_Votes union all select MVP_2 as name, 2 as score from IBL_EOY_Votes union all select MVP_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
		$result1=mysql_query($query1);
		$num1=mysql_num_rows($result1);
		$query15="select sum(score) as votes,name from (select MVP_1 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
		$result15=mysql_query($query15);
		$num15=mysql_num_rows($result15);

		$query2="select sum(score) as votes,name from (select Six_1 as name, 3 as score from IBL_EOY_Votes union all select Six_2 as name, 2 as score from IBL_EOY_Votes union all select Six_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
		$result2=mysql_query($query2);
		$num2=mysql_num_rows($result2);
		$query3="select sum(score) as votes,name from (select ROY_1 as name, 3 as score from IBL_EOY_Votes union all select ROY_2 as name, 2 as score from IBL_EOY_Votes union all select ROY_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
		$result3=mysql_query($query3);
		$num3=mysql_num_rows($result3);
		$query4="select sum(score) as votes,name from (select GM_1 as name, 3 as score from IBL_EOY_Votes union all select GM_2 as name, 2 as score from IBL_EOY_Votes union all select GM_3 as name, 1 as score from IBL_EOY_Votes) as tbl group by name;";
		$result4=mysql_query($query4);
		$num4=mysql_num_rows($result4);
		$query5="select count(name) as votes,name from (select MVP_1 as name from IBL_EOY_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result5=mysql_query($query5);
		$num5=mysql_num_rows($result5);
		$query6="select count(name) as votes,name from (select Six_1 as name from IBL_EOY_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result6=mysql_query($query6);
		$num6=mysql_num_rows($result6);
		$query7="select count(name) as votes,name from (select ROY_1 as name from IBL_EOY_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result7=mysql_query($query7);
		$num7=mysql_num_rows($result7);
		$query8="select count(name) as votes,name from (select GM_1 as name from IBL_EOY_Votes) as tbl group by name having count(name) > 0 order by 1 desc;";
		$result8=mysql_query($query8);
		$num8=mysql_num_rows($result8);

		OpenTable();
		$k=0;
		$h=0;
		$i=0;
		$m=0;
		$w=0;
		$x=0;
		$y=0;
		$z=0;

		while ($k < $num1) {
			$player[$k]=mysql_result($result1,$k, "name");
			$votes[$k]=mysql_result($result1,$k);

			$table_echo=$table_echo."<tr><td>".$player[$k]."</td><td>".$votes[$k]."</td></tr>";
			$k++;
		}

		$text=$text."<table class=\"sortable\" border=1><tr><th>Player</th><th> Score</th></tr>$table_echo</table><br><br>";

		while ($h < $num2) {
			$player[$h]=mysql_result($result2,$h, "name");
			$votes[$h]=mysql_result($result2,$h);
			$table_echo1=$table_echo1."<tr><td>".$player[$h]."</td><td>".$votes[$h]."</td></tr>";
			$h++;
		}
		$text1=$text1."<table class=\"sortable\" border=1><tr><th>Player</th><th> Score</th></tr>$table_echo1</table><br><br>";
		while ($i < $num3) {
			$player[$i]=mysql_result($result3,$i, "name");
			$votes[$i]=mysql_result($result3,$i);
			$table_echo2=$table_echo2."<tr><td>".$player[$i]."</td><td>".$votes[$i]."</td></tr>";
			$i++;
		}
		$text2=$text2."<table class=\"sortable\" border=1><tr><th>Player</th><th> Score</th></tr>$table_echo2</table><br><br>";
		while ($m < $num4) {
			$player[$m]=mysql_result($result4,$m, "name");
			$votes[$m]=mysql_result($result4,$m);
			$table_echo3=$table_echo3."<tr><td>".$player[$m]."</td><td>".$votes[$m]."</td></tr>";
			$m++;
		}
		$text3=$text3."<table class=\"sortable\" border=1><tr><th>GM</th><th> Score</th></tr>$table_echo3</table><br><br>";
		while ($w < $num5) {
			$player[$w]=mysql_result($result5,$w, "name");
			$votes[$w]=mysql_result($result5,$w);
			$table_echo5=$table_echo5."<tr><td>".$player[$w]."</td><td>".$votes[$w]."</td></tr>";
			$w++;
		}

		$text5=$text5."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo5</table><br><br>";
		while ($x < $num6) {
			$player[$x]=mysql_result($result6,$x, "name");
			$votes[$x]=mysql_result($result6,$x);
			$table_echo6=$table_echo6."<tr><td>".$player[$x]."</td><td>".$votes[$x]."</td></tr>";
			$x++;
		}

		$text6=$text6."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo6</table><br><br>";
		while ($y < $num7) {
			$player[$y]=mysql_result($result7,$y, "name");
			$votes[$y]=mysql_result($result7,$y);
			$table_echo7=$table_echo7."<tr><td>".$player[$y]."</td><td>".$votes[$y]."</td></tr>";
			$y++;
		}

		$text7=$text7."<table class=\"sortable\" border=1><tr><th>Player</th><th> Votes</th></tr>$table_echo7</table><br><br>";
		while ($z < $num8) {
			$player[$z]=mysql_result($result8,$z, "name");
			$votes[$z]=mysql_result($result8,$z);
			$table_echo8=$table_echo8."<tr><td>".$player[$z]."</td><td>".$votes[$z]."</td></tr>";
			$z++;
		}

		$text8=$text8."<table class=\"sortable\" border=1><tr><th>GM</th><th> Votes</th></tr>$table_echo8</table><br><br>";

		echo "<b>Most Valuable Player (Total)</b>";
		echo $text;
		echo "<b>Most Valuable Player (1st Place Votes)</b>";
		echo $text5;
		echo "<b>Sixth Man of the Year (Total)</b>";
		echo $text1;
		echo "<b>Sixth Man of the Year (1st Place Votes)</b>";
		echo $text6;
		echo "<b>Rookie of the Year (Total)</b>";
		echo $text2;
		echo "<b>Rookie of the Year (1st Place Votes)</b>";
		echo $text7;
		echo "<b>GM of the Year (Total)</b>";
		echo $text3;
		echo "<b>GM of the Year (1st Place Votes)</b>";
		echo $text8;
		CloseTable();
		include("footer.php");
	}
}

function eoy_voters()
{
	include("header.php");
	$query2="SELECT * FROM ibl_team_history WHERE teamid != 35 ORDER BY teamid ASC";
	$result2=mysql_query($query2);
	$num2=mysql_num_rows($result2);

	OpenTable();
	$k=0;
	while ($k < $num2) {
		$teamname[$k]=mysql_result($result2,$k,"team_name");
		$teamcity[$k]=mysql_result($result2,$k,"team_city");
		$teamcolor1[$k]=mysql_result($result2,$k,"color1");
		$teamcolor2[$k]=mysql_result($result2,$k,"color2");
		$eoy_vote[$k]=mysql_result($result2,$k,"eoy_vote");
		$teamid[$k]=mysql_result($result2,$k,"teamid");

		$table_echo=$table_echo."<tr><td bgcolor=#".$teamcolor1[$k]."><a href=\"./modules.php?name=Team&op=team&tid=".$teamid[$k]."\"><font color=#".$teamcolor2[$k].">".$teamcity[$k]." ".$teamname[$k]."</a></td><td>".$eoy_vote[$k]."</td></tr>";
		$k++;
	}
	$text=$text."<table class=\"sortable\" border=1><tr><th>Team</th><th>Vote Received</th></tr>$table_echo</table>";
	echo $text;
	CloseTable();
	include("footer.php");
}

function asg_voters()
{
	include("header.php");
	$query2="SELECT * FROM ibl_team_history WHERE teamid != 35 ORDER BY teamid ASC";
	$result2=mysql_query($query2);
	$num2=mysql_num_rows($result2);

	OpenTable();
	$k=0;
	while ($k < $num2) {
		$teamname[$k]=mysql_result($result2,$k,"team_name");
		$teamcity[$k]=mysql_result($result2,$k,"team_city");
		$teamcolor1[$k]=mysql_result($result2,$k,"color1");
		$teamcolor2[$k]=mysql_result($result2,$k,"color2");
		$asg_vote[$k]=mysql_result($result2,$k,"asg_vote");
		$teamid[$k]=mysql_result($result2,$k,"teamid");

		$table_echo=$table_echo."<tr><td bgcolor=#".$teamcolor1[$k]."><a href=\"./modules.php?name=Team&op=team&tid=".$teamid[$k]."\"><font color=#".$teamcolor2[$k].">".$teamcity[$k]." ".$teamname[$k]."</a></td><td>".$asg_vote[$k]."</td></tr>";
		$k++;
	}
	$text=$text."<table class=\"sortable\" border=1><tr><th>Team</th><th>Vote Received</th></tr>$table_echo</table>";
	echo $text;
	CloseTable();
	include("footer.php");
}

switch($op) {
	case "changeset":
	changeset($user);
	break;

	case "leaguestats":
	leaguestats();
	break;

	case "seteditor":
	seteditor($user);
	break;

	case "training":
	viewtraining($user);
	break;

	case "team":
	team($tid);
	break;

	case "finances":
	finances($tid);
	break;

	case "schedule":
	schedule($tid);
	break;

	case "injuries":
	viewinjuries($tid);
	break;

	case "drafthistory":
	drafthistory($tid);
	break;

	case "asg_voting":
	asg_voting();
	break;
	case "eoy_voting":
	eoy_voting();
	break;
	case "asg_results":
	asg_results();
	break;
	case "eoy_results":
	eoy_results();
	break;
	case "eoy_voters":
	eoy_voters();
	break;
	case "asg_voters":
	asg_voters();
	break;

	default:
	menu();
	break;
}
// Remove the double-slashes from the cases above for the off-season
?>
